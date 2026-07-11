<?php

namespace Tests\Feature\Affiliate;

use App\Enums\AffiliateStatus;
use App\Models\Entities\Affiliate;
use App\Models\Entities\AffiliateClick;
use App\Services\ConfigDbService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base cho các test affiliate (Phase 6 — AFFILIATE-PLAN.md).
 *
 * Theo pattern StockOversellTest: tự dựng schema tối thiểu trên sqlite
 * :memory: (không phụ thuộc chuỗi migration), ghi đè ConfigDbService bằng
 * mảng $configDb — test chỉnh trực tiếp property này trước khi gọi service.
 * Core config (getCoreConfig) override runtime qua config(['core.config...']).
 */
abstract class AffiliateTestCase extends TestCase
{
    /** Fake bảng setting — public để anonymous class đọc được. */
    public array $configDb = [
        'config_affiliate_enabled'         => 1,
        'config_affiliate_commission_rate' => 5,
        'config_affiliate_cookie_days'     => 30,
        'config_affiliate_hold_days'       => 7,
        'config_affiliate_min_payout'      => 200000,
        'config_affiliate_auto_approve'    => 0,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $test = $this;
        $this->app->bind(ConfigDbService::class, function () use ($test) {
            return new class ($test) extends ConfigDbService {
                public function __construct(private AffiliateTestCase $test)
                {
                }

                public function getConfigs(): array
                {
                    return $this->test->configDb;
                }
            };
        });

        $this->createAffiliateSchema();
    }

    protected function createAffiliateSchema(): void
    {
        Schema::create('affiliate', function ($t) {
            $t->integer('id', true);
            $t->unsignedBigInteger('user_id');
            $t->string('code', 32);
            $t->tinyInteger('status')->default(0);
            $t->decimal('commission_rate', 5, 2)->nullable();
            $t->json('payment_info')->nullable();
            $t->integer('clicks_count')->default(0);
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('affiliate_link', function ($t) {
            $t->integer('id', true);
            $t->integer('affiliate_id');
            $t->string('slug', 10);
            $t->string('destination_url', 512);
            $t->integer('product_id')->nullable();
            $t->string('sub_id', 64)->nullable();
            $t->integer('clicks_count')->default(0);
            $t->timestamps();
        });

        Schema::create('affiliate_click', function ($t) {
            $t->bigIncrements('id');
            $t->integer('affiliate_id');
            $t->integer('affiliate_link_id')->nullable();
            $t->string('click_token', 16);
            $t->string('sub_id', 64)->nullable();
            $t->string('session_id', 64)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->string('landing_url', 512)->nullable();
            $t->string('referrer', 512)->nullable();
            $t->string('utm_source', 64)->nullable();
            $t->string('utm_medium', 64)->nullable();
            $t->string('utm_campaign', 64)->nullable();
            $t->integer('product_id')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('affiliate_conversion', function ($t) {
            $t->integer('id', true);
            $t->integer('affiliate_id');
            $t->integer('order_id');
            $t->bigInteger('click_id')->nullable();
            $t->string('coupon_code', 20)->nullable();
            $t->integer('order_total')->default(0);
            $t->integer('commission')->default(0);
            $t->decimal('commission_rate', 5, 2)->nullable();
            $t->tinyInteger('status')->default(0);
            $t->integer('payout_id')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('coupon', function ($t) {
            $t->integer('id', true);
            $t->string('code', 20);
            $t->tinyInteger('type')->default(0);
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('affiliate_coupon', function ($t) {
            $t->integer('affiliate_id');
            $t->integer('coupon_id');
        });

        Schema::create('affiliate_commission_rule', function ($t) {
            $t->integer('id', true);
            $t->integer('category_id');
            $t->decimal('rate', 5, 2);
            $t->timestamps();
        });

        // Tối thiểu cho AffiliateConversionService: set orders.affiliate_id
        // (query-builder update, không model event) + map SP → category.
        // Orders dùng SoftDeletes (scope deleted_at) và update qua model
        // query tự touch updated_at → cần đủ 3 cột này.
        Schema::create('orders', function ($t) {
            $t->integer('id', true);
            $t->integer('affiliate_id')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });

        Schema::create('product_category', function ($t) {
            $t->integer('product_id');
            $t->integer('category_id');
        });
    }

    protected function makeAffiliate(array $attrs = []): Affiliate
    {
        return Affiliate::create(array_merge([
            'user_id' => 1,
            'code'    => strtolower(Str::random(8)),
            'status'  => AffiliateStatus::Active->value,
        ], $attrs));
    }

    protected function makeClick(Affiliate $affiliate, array $attrs = []): AffiliateClick
    {
        return AffiliateClick::create(array_merge([
            'affiliate_id' => $affiliate->id,
            'click_token'  => Str::random(12),
            'session_id'   => 'sess-test',
            'created_at'   => now(),
        ], $attrs));
    }

    /**
     * getCurrentUserId() trả null khi runningInConsole (PHPUnit là console) —
     * ép app về "web context" bằng reflection để test self-referral.
     */
    protected function forceWebContext(): void
    {
        $prop = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $prop->setAccessible(true);
        $prop->setValue($this->app, false);
    }
}
