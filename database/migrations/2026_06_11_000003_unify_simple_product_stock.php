<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unify simple-product stock under the product_stock cluster.
 *
 * Why
 * ---
 * Before this migration the codebase had two stock sources living side by
 * side: variant products read product_stock.on_hand/reserved, while simple
 * products read the legacy product.quantity column gated by product.subtract.
 * That split caused real bugs — the in_stock filter only checked
 * product.quantity, CartService::checkStock / CreateOrderService::subtractStock
 * each carried an "if (variant) ... else ..." branch, and simple products had
 * no concept of `reserved` so concurrent orders could oversell.
 *
 * The fix follows the Shopify / Saleor pattern: every product, including
 * "simple" ones, owns at least one product_variant row (the default variant)
 * plus a matching product_stock row. The cart, checkout and order pipelines
 * always go through product_stock — no more branching.
 *
 * Backorder support ("bán khống")
 * --------------------------------
 * The legacy product_stock.subtract boolean covered "track / don't track"
 * but couldn't express "track AND allow oversell" (i.e. backorder). This
 * migration adds an explicit inventory_policy enum:
 *
 *   0 = deny       — block sale when on_hand - reserved <= 0 (default)
 *   1 = backorder  — allow sale even when on_hand goes negative; admin
 *                    sees the backlog via the stock_movement audit log
 *   2 = untracked  — never decrement; for digital goods / services /
 *                    drop-shipped items where on_hand is meaningless
 *
 * Existing rows are backfilled from `subtract`:
 *   subtract = true  → policy = 0 (deny)     — same effective behaviour
 *   subtract = false → policy = 2 (untracked) — same effective behaviour
 *
 * What stays untouched
 * --------------------
 *  - product.has_variants  : kept as a UI fast-path flag. Simple products
 *                            stay has_variants = 0 so the detail page does
 *                            not render variant pickers for a hidden default.
 *  - product.quantity      : kept during the observe phase. CartService /
 *                            CreateOrderService will stop reading it once
 *                            this migration ships; drop it in a follow-up.
 *  - product.subtract      : same. Stays as a legacy fallback for any caller
 *                            we may have missed.
 *  - has_variants update   : the previous migration 100007 set has_variants
 *                            = 1 for products that had legacy variants. This
 *                            migration explicitly DOES NOT flip has_variants
 *                            for the default-variant backfill — that flag is
 *                            now UI-only.
 *
 * Idempotency
 * -----------
 * Safe to re-run: the inventory_policy column is added only if missing, and
 * the default-variant backfill skips any product that already has at least
 * one product_variant row.
 */
return new class extends Migration
{
    /** product_stock.inventory_policy values; mirror ProductStock::POLICY_*. */
    private const POLICY_DENY = 0;

    private const POLICY_UNTRACKED = 2;

    private const DEFAULT_WAREHOUSE_ID = 1;

    public function up(): void
    {
        if (! Schema::hasTable('product_stock')) {
            return; // cluster variant migrations have not run; nothing to do
        }

        // 1) Add the inventory_policy enum + backfill from `subtract`.
        if (! Schema::hasColumn('product_stock', 'inventory_policy')) {
            Schema::table('product_stock', function (Blueprint $table) {
                $table->unsignedTinyInteger('inventory_policy')
                    ->default(self::POLICY_DENY)
                    ->after('subtract')
                    ->comment('0=deny, 1=backorder, 2=untracked');
            });

            // Map legacy subtract → policy. Existing rows keep the same
            // effective behaviour they had before this migration.
            DB::table('product_stock')->where('subtract', false)
                ->update(['inventory_policy' => self::POLICY_UNTRACKED]);
            DB::table('product_stock')->where('subtract', true)
                ->update(['inventory_policy' => self::POLICY_DENY]);
        }

        // 2) Backfill default variant + stock row for every simple product
        //    that doesn't already have at least one product_variant.
        $this->backfillSimpleProducts();
    }

    public function down(): void
    {
        // Drop the column. The default-variant rows are intentionally NOT
        // rolled back — they are perfectly valid Shopify-style data; removing
        // them would break any new cart line that already points at one.
        if (Schema::hasColumn('product_stock', 'inventory_policy')) {
            Schema::table('product_stock', function (Blueprint $table) {
                $table->dropColumn('inventory_policy');
            });
        }
    }

    /**
     * Create one default variant + one product_stock row per simple product.
     *
     * Selection: any product whose id is not present in product_variant.
     * Includes both legacy "pure simple" products (has_variants = 0 from the
     * start) and any rare drift cases that lost their variants. The chunked
     * cursor keeps memory flat on catalogues with 100k+ products.
     */
    private function backfillSimpleProducts(): void
    {
        DB::transaction(function () {
            DB::table('product as p')
                ->leftJoin('product_variant as pv', 'pv.product_id', '=', 'p.id')
                ->whereNull('pv.id')
                ->whereNull('p.deleted_at')
                ->select(['p.id', 'p.price', 'p.weight', 'p.image', 'p.quantity', 'p.subtract'])
                ->orderBy('p.id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $this->createDefaultVariant($row);
                    }
                }, 'p.id', 'id');
        });
    }

    private function createDefaultVariant(object $row): void
    {
        $productId = (int) $row->id;

        // attribute_signature for the default (no-attribute) variant. Cluster
        // schema enforces UNIQUE(product_id, attribute_signature), so we use
        // a constant marker so a re-run hits the existing row instead of
        // failing — the chunk filter above already excludes products that
        // own a variant, but a parallel admin import could race in here.
        $signature = md5('__default__');

        $variantId = DB::table('product_variant')->insertGetId([
            'product_id'          => $productId,
            'sku'                 => null,
            'attribute_signature' => $signature,
            'price'               => (float) ($row->price ?? 0),
            'points'              => 0,
            'weight'              => $row->weight === null ? null : (float) $row->weight,
            'image'               => $row->image,
            'is_default'          => 1,
            'sort_order'          => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $onHand = (int) ($row->quantity ?? 0);
        $policy = ((int) ($row->subtract ?? 1)) === 1
            ? self::POLICY_DENY
            : self::POLICY_UNTRACKED;

        DB::table('product_stock')->insert([
            'product_variant_id' => $variantId,
            'warehouse_id'       => self::DEFAULT_WAREHOUSE_ID,
            'on_hand'            => $onHand,
            'reserved'           => 0,
            'subtract'           => $policy === self::POLICY_UNTRACKED ? 0 : 1,
            'inventory_policy'   => $policy,
            'version'            => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Seed the audit log so admin tools can rebuild on_hand from
        // stock_movement alone — mirrors the legacy-variant migration.
        DB::table('stock_movement')->insert([
            'product_variant_id' => $variantId,
            'warehouse_id'       => self::DEFAULT_WAREHOUSE_ID,
            'type'               => 'receive',
            'quantity_change'    => $onHand,
            'on_hand_after'      => $onHand,
            'reference_type'     => 'migration',
            'reference_id'       => null,
            'user_id'            => null,
            'note'               => 'Default variant backfilled for simple product',
            'created_at'         => now(),
        ]);
    }
};
