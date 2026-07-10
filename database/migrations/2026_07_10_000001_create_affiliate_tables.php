<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate Phase 1 (2026-07-10) — xem AFFILIATE-PLAN.md.
 *
 * Business đã chốt: commission trên (sub_total - discount, TRƯỚC ship); rate
 * admin chỉnh được + override theo ngành hàng (affiliate_commission_rule);
 * last-click 30 ngày (link KOL khác override); payout chuyển khoản trước,
 * ZaloPay sau (payment_info JSON chứa cả hai); KOL có coupon riêng
 * (affiliate_coupon); short link site.vn/l/{slug}.
 *
 * Kiểu cột FK khớp legacy: user.id BIGINT UNSIGNED; coupon/category/orders.id
 * INT SIGNED → PK các bảng affiliate dùng INT SIGNED để orders.affiliate_id
 * (int(11) sẵn có) FK được.
 *
 * Dọn cột orders: DROP tracking/commission/marketing_id (OpenCart vestigial,
 * không code reference — đã kiểm 2026-07-10); GIỮ affiliate_id, thêm FK
 * SET NULL (dọn giá trị 0/orphan về NULL trước).
 */
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('affiliate')) {
            Schema::create('affiliate', function (Blueprint $table) {
                $table->integer('id', true);
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('code', 32)->unique();
                $table->tinyInteger('status')->default(0);          // enum AffiliateStatus
                $table->decimal('commission_rate', 5, 2)->nullable(); // NULL = config global
                $table->json('payment_info')->nullable();            // bank / zalopay
                $table->integer('clicks_count')->default(0);
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'fk_affiliate_user')
                    ->references('id')->on('user')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('affiliate_link')) {
            Schema::create('affiliate_link', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('affiliate_id');
                $table->string('slug', 10)->unique();
                $table->string('destination_url', 512);
                $table->integer('product_id')->nullable();
                $table->string('sub_id', 64)->nullable();
                $table->integer('clicks_count')->default(0);
                $table->timestamps();

                $table->foreign('affiliate_id', 'fk_affiliate_link_affiliate')
                    ->references('id')->on('affiliate')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('affiliate_click')) {
            Schema::create('affiliate_click', function (Blueprint $table) {
                $table->bigInteger('id', true);
                $table->integer('affiliate_id');
                $table->integer('affiliate_link_id')->nullable();
                $table->string('click_token', 16)->unique();
                $table->string('sub_id', 64)->nullable();
                $table->string('session_id', 64)->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('landing_url', 512)->nullable();
                $table->string('referrer', 512)->nullable();
                $table->string('utm_source', 64)->nullable();
                $table->string('utm_medium', 64)->nullable();
                $table->string('utm_campaign', 64)->nullable();
                $table->integer('product_id')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['affiliate_id', 'created_at'], 'idx_aff_click_aff_time');
                $table->index('session_id', 'idx_aff_click_session');
                $table->foreign('affiliate_id', 'fk_affiliate_click_affiliate')
                    ->references('id')->on('affiliate')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('affiliate_conversion')) {
            Schema::create('affiliate_conversion', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('affiliate_id');
                $table->integer('order_id')->unique();               // 1 đơn = 1 affiliate (last-click)
                $table->bigInteger('click_id')->nullable();
                $table->string('coupon_code', 20)->nullable();
                $table->integer('order_total')->default(0);          // snapshot: sau discount, TRƯỚC ship, base currency
                $table->integer('commission')->default(0);           // snapshot tiền hoa hồng, base currency
                $table->decimal('commission_rate', 5, 2)->nullable(); // rate hiệu dụng lúc tính (audit)
                $table->tinyInteger('status')->default(0);           // enum AffiliateConversionStatus
                $table->integer('payout_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['affiliate_id', 'status'], 'idx_aff_conv_aff_status');
                $table->foreign('affiliate_id', 'fk_affiliate_conv_affiliate')
                    ->references('id')->on('affiliate')->cascadeOnDelete();
                $table->foreign('order_id', 'fk_affiliate_conv_order')
                    ->references('id')->on('orders')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('affiliate_payout')) {
            Schema::create('affiliate_payout', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('affiliate_id');
                $table->string('period', 7);                         // '2026-07'
                $table->integer('amount')->default(0);
                $table->tinyInteger('status')->default(0);           // enum AffiliatePayoutStatus
                $table->timestamp('paid_at')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['affiliate_id', 'period'], 'uq_aff_payout_period');
                $table->foreign('affiliate_id', 'fk_affiliate_payout_affiliate')
                    ->references('id')->on('affiliate')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('affiliate_coupon')) {
            Schema::create('affiliate_coupon', function (Blueprint $table) {
                $table->integer('affiliate_id');
                $table->integer('coupon_id');
                $table->primary(['affiliate_id', 'coupon_id']);

                $table->foreign('affiliate_id', 'fk_affiliate_coupon_affiliate')
                    ->references('id')->on('affiliate')->cascadeOnDelete();
                $table->foreign('coupon_id', 'fk_affiliate_coupon_coupon')
                    ->references('id')->on('coupon')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('affiliate_commission_rule')) {
            Schema::create('affiliate_commission_rule', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('category_id')->unique();            // 1 rule / ngành hàng (global)
                $table->decimal('rate', 5, 2);
                $table->timestamps();

                $table->foreign('category_id', 'fk_aff_rule_category')
                    ->references('id')->on('category')->cascadeOnDelete();
            });
        }

        // ----- Dọn orders: drop 3 cột vestigial, FK hoá affiliate_id -----
        foreach (['tracking', 'commission', 'marketing_id'] as $col) {
            if (Schema::hasColumn('orders', $col)) {
                Schema::table('orders', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }

        if (Schema::hasColumn('orders', 'affiliate_id') && ! $this->foreignKeyExists('orders', 'fk_orders_affiliate')) {
            DB::statement(
                'UPDATE orders o
                    LEFT JOIN affiliate a ON a.id = o.affiliate_id
                    SET o.affiliate_id = NULL
                    WHERE o.affiliate_id IS NOT NULL AND (o.affiliate_id = 0 OR a.id IS NULL)'
            );
            Schema::table('orders', function (Blueprint $t) {
                $t->foreign('affiliate_id', 'fk_orders_affiliate')
                    ->references('id')->on('affiliate')->nullOnDelete();
            });
        }

        // ----- Settings seed -----
        $settings = [
            'config_affiliate_enabled'         => '0',
            'config_affiliate_commission_rate' => '5',
            'config_affiliate_cookie_days'     => '30',
            'config_affiliate_hold_days'       => '7',
            'config_affiliate_min_payout'      => '200000',
            'config_affiliate_auto_approve'    => '0',
        ];
        foreach ($settings as $key => $value) {
            DB::table('setting')->insertOrIgnore([
                'code'       => 'config',
                'key'        => $key,
                'value'      => $value,
                'serialized' => 0,
                'system'     => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget(getCoreConfig('cache.setting'));
        $this->flushSchemaCache();
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('orders', 'fk_orders_affiliate')) {
            DB::statement('ALTER TABLE orders DROP FOREIGN KEY fk_orders_affiliate');
        }
        Schema::table('orders', function (Blueprint $t) {
            $t->string('tracking', 64)->nullable();
            $t->decimal('commission', 15, 4)->nullable();
            $t->integer('marketing_id')->nullable();
        });

        Schema::dropIfExists('affiliate_commission_rule');
        Schema::dropIfExists('affiliate_coupon');
        Schema::dropIfExists('affiliate_payout');
        Schema::dropIfExists('affiliate_conversion');
        Schema::dropIfExists('affiliate_click');
        Schema::dropIfExists('affiliate_link');
        Schema::dropIfExists('affiliate');

        DB::table('setting')->whereIn('key', [
            'config_affiliate_enabled',
            'config_affiliate_commission_rate',
            'config_affiliate_cookie_days',
            'config_affiliate_hold_days',
            'config_affiliate_min_payout',
            'config_affiliate_auto_approve',
        ])->delete();

        Cache::forget(getCoreConfig('cache.setting'));
        $this->flushSchemaCache();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    /** HasSchemaCache cache cột forever — orders đổi cột phải flush. */
    private function flushSchemaCache(): void
    {
        $conn = DB::connection();
        Cache::forget(implode(':', [
            'schema',
            $conn->getDatabaseName(),
            $conn->getDriverName(),
            'orders',
        ]));
    }
};
