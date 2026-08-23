<?php

namespace App\Console\Commands;

use App\Enums\AffiliateConversionStatus;
use App\Enums\AffiliateStatus;
use App\Enums\UserType;
use App\Models\Entities\Affiliate;
use App\Models\Entities\AffiliateClick;
use App\Models\Entities\AffiliateConversion;
use App\Models\Entities\AffiliateLink;
use App\Models\Entities\Coupon;
use App\Models\Entities\Orders;
use App\Models\Entities\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dựng dữ liệu DEMO cho luồng affiliate — để test màn CMS Phase 5
 * (docs/AFFILIATE-PLAN.md) mà không phải chờ có KOL thật đăng ký và đặt đơn.
 *
 * Vì sao là command chứ không phải Seeder: dữ liệu này phải DỌN ĐƯỢC. Seeder
 * chỉ có `run()`, chạy xong để lại rác trong DB dev không ai nhớ dòng nào là
 * giả. `--clean` ở đây xoá đúng những gì command tạo ra, nhận diện qua email
 * marker + mã ref.
 *
 * Conversion GẮN VÀO ĐƠN CÓ SẴN thay vì tạo đơn mới: bảng `orders` có vài
 * chục cột và cả chuỗi bảng con (orders_product/total/history) — dựng một đơn
 * giả cho đủ hợp lệ tốn công hơn nhiều so với giá trị nó mang lại, trong khi
 * `affiliate_conversion.order_id` chỉ cần một đơn thật để FK bám vào.
 *
 * Bộ dữ liệu cố ý có cả trường hợp KHÔNG đủ điều kiện chi trả:
 *  - Approved nhưng approved_at mới hôm qua → chưa qua hold_days, chốt kỳ
 *    phải BỎ QUA. Đây là thứ dễ làm sai nhất và cũng khó phát hiện nhất khi
 *    chỉ seed toàn dữ liệu "đẹp".
 *  - Pending (đơn chưa giao) và Rejected (đơn huỷ) → không bao giờ vào kỳ.
 */
class SeedAffiliateDemoCommand extends Command
{
    protected $signature = 'affiliate:seed-demo
        {--clean : Xoá sạch dữ liệu demo thay vì tạo mới}
        {--email=affiliate-demo@infun.test : Email KOL demo — cũng là marker để dọn}
        {--code=demokol1 : Mã ref của KOL demo}
        {--rate=10 : Tỷ lệ hoa hồng riêng của KOL demo (%)}';

    protected $description = 'Tạo (hoặc xoá) dữ liệu demo affiliate: KOL, link, click, conversion đủ/chưa đủ điều kiện chi trả';

    public function handle(): int
    {
        // Dữ liệu giả trong DB thật là thứ không sửa lại được bằng một câu
        // lệnh. Chặn ở đây rẻ hơn nhiều so với dọn hậu quả.
        if (app()->isProduction()) {
            $this->error('Từ chối chạy trên môi trường production.');

            return self::FAILURE;
        }

        return $this->option('clean') ? $this->clean() : $this->seed();
    }

    // ===================== TẠO =====================

    private function seed(): int
    {
        $email = (string) $this->option('email');
        $code  = (string) $this->option('code');
        $rate  = (float) $this->option('rate');

        $user      = $this->ensureUser($email);
        $affiliate = $this->ensureAffiliate($user, $code, $rate);
        $link      = $this->ensureLink($affiliate);

        $clicks = $this->seedClicks($affiliate, $link);
        $coupon = $this->attachCoupon($affiliate);
        $report = $this->seedConversions($affiliate, $rate);

        $this->newLine();
        $this->info('Đã dựng dữ liệu demo affiliate:');
        $this->table(['Mục', 'Giá trị'], [
            ['KOL', $user->full_name . ' <' . $user->email . '>'],
            ['affiliate.id', $affiliate->id],
            ['Mã ref', $affiliate->code],
            ['Tỷ lệ riêng', $rate . '%'],
            ['Short link', route('affiliate.redirect', ['slug' => $link->slug])],
            ['Click', $clicks],
            ['Coupon riêng', $coupon?->code ?? '(không có coupon nào để gán)'],
            ['Conversion đủ điều kiện chi trả', $report['payable_count'] . ' đơn / ' . number_format($report['payable_sum']) . ' đ'],
            ['Conversion chưa qua hold', $report['held_count'] . ' đơn'],
            ['Conversion Pending / Rejected', $report['pending_count'] . ' / ' . $report['rejected_count']],
        ]);

        $min = (int) getConfigDb('config_affiliate_min_payout');
        if ($report['payable_sum'] < $min) {
            $this->warn(sprintf(
                'Tổng đủ điều kiện (%s đ) THẤP HƠN ngưỡng chi trả tối thiểu (%s đ) — chốt kỳ sẽ bỏ qua KOL này.',
                number_format($report['payable_sum']),
                number_format($min),
            ));
            $this->line('  Hạ ngưỡng trong CMS (Cấu hình → Reward & Affiliate) hoặc seed thêm đơn để thử luồng chốt kỳ.');
        } else {
            $this->line('Thử tiếp: php artisan affiliate:close-period --dry   (xem trước, không ghi gì)');
        }

        if ((int) getConfigDb('config_affiliate_enabled') !== 1) {
            $this->warn('config_affiliate_enabled đang TẮT — màn CMS vẫn dùng được, nhưng tracking click/attribution ở storefront thì không.');
        }

        $this->line('Dọn sạch: php artisan affiliate:seed-demo --clean');

        return self::SUCCESS;
    }

    private function ensureUser(string $email): User
    {
        $user = User::withTrashed()->where('email', $email)->first();
        if ($user) {
            $this->line('Dùng lại user demo đã có: ' . $email);

            return $user;
        }

        return User::create([
            'username'  => 'affiliate_demo',
            'email'     => $email,
            'password'  => Hash::make(Str::random(16)),
            'full_name' => 'KOL Demo',
            'status'    => 1,
            'type'      => UserType::Member->value,
        ]);
    }

    private function ensureAffiliate(User $user, string $code, float $rate): Affiliate
    {
        $affiliate = Affiliate::where('user_id', $user->id)->first() ?? new Affiliate();

        $affiliate->user_id         = $user->id;
        $affiliate->code            = $code;
        $affiliate->status          = AffiliateStatus::Active->value;
        $affiliate->commission_rate = $rate;
        $affiliate->payment_info    = [
            'bank_name'     => 'Vietcombank',
            'bank_account'  => '0123456789',
            'bank_holder'   => 'KOL DEMO',
            'zalopay_phone' => '0900000000',
        ];
        $affiliate->approved_at ??= now()->subDays(60);
        $affiliate->save();

        return $affiliate;
    }

    private function ensureLink(Affiliate $affiliate): AffiliateLink
    {
        $link = AffiliateLink::where('affiliate_id', $affiliate->id)->first();
        if ($link) {
            return $link;
        }

        return AffiliateLink::create([
            'affiliate_id'    => $affiliate->id,
            'slug'            => Str::random(8),
            'destination_url' => '/san-pham',
            'product_id'      => null,
            'sub_id'          => 'tiktok',
        ]);
    }

    /**
     * Click rải đều 14 ngày để chart trong cổng KOL có hình dạng, không phải
     * một cột dựng đứng ở hôm nay.
     */
    private function seedClicks(Affiliate $affiliate, AffiliateLink $link): int
    {
        $existing = AffiliateClick::where('affiliate_id', $affiliate->id)->count();
        if ($existing > 0) {
            $this->line("Đã có {$existing} click, bỏ qua bước tạo click.");

            return $existing;
        }

        $subIds = ['tiktok', 'instagram', null];
        $total  = 0;

        for ($day = 13; $day >= 0; $day--) {
            $perDay = 1 + ($day % 3);
            for ($i = 0; $i < $perDay; $i++) {
                AffiliateClick::create([
                    'affiliate_id'      => $affiliate->id,
                    'affiliate_link_id' => $link->id,
                    'click_token'       => Str::random(12),
                    'sub_id'            => $subIds[$i % count($subIds)],
                    'session_id'        => Str::random(32),
                    'ip'                => '127.0.0.' . (10 + $i),
                    'user_agent'        => 'AffiliateDemoSeeder/1.0',
                    'landing_url'       => '/san-pham',
                    'referrer'          => 'https://www.tiktok.com/',
                    'utm_source'        => 'aff_' . $affiliate->code,
                    'utm_medium'        => 'affiliates',
                    'utm_campaign'      => 'link_' . $link->slug,
                    'created_at'        => now()->subDays($day)->setTime(9 + $i, 15),
                ]);
                $total++;
            }
        }

        // clicks_count là aggregate cache (recordClick tăng khi chạy thật) —
        // seed thì phải tự đặt cho khớp, không thì dashboard KOL hiện 0 click
        // trong khi bảng click có dữ liệu.
        $affiliate->clicks_count = $total;
        $affiliate->save();
        $link->clicks_count = $total;
        $link->save();

        return $total;
    }

    private function attachCoupon(Affiliate $affiliate): ?Coupon
    {
        $coupon = Coupon::query()->whereNull('deleted_at')->orderBy('id')->first();
        if (! $coupon) {
            return null;
        }

        // Một coupon chỉ thuộc về một KOL — gỡ khỏi KOL khác trước, đúng như
        // AffiliateRepository::syncCoupons làm.
        DB::table('affiliate_coupon')
            ->where('coupon_id', $coupon->id)
            ->where('affiliate_id', '!=', $affiliate->id)
            ->delete();

        DB::table('affiliate_coupon')->insertOrIgnore([
            'affiliate_id' => $affiliate->id,
            'coupon_id'    => $coupon->id,
        ]);

        return $coupon;
    }

    /**
     * Gắn conversion vào các đơn CHƯA có conversion nào (order_id UNIQUE).
     *
     * Số đơn Approved-đã-qua-hold được lấy vừa đủ để tổng vượt
     * `config_affiliate_min_payout` — để `affiliate:close-period` thật sự tạo
     * ra một kỳ chi trả thay vì báo "chưa đạt ngưỡng" rồi không có gì để xem.
     *
     * @return array{payable_count:int, payable_sum:int, held_count:int, pending_count:int, rejected_count:int}
     */
    private function seedConversions(Affiliate $affiliate, float $rate): array
    {
        $taken = AffiliateConversion::query()->pluck('order_id')->all();

        $orders = Orders::query()
            ->whereNotIn('id', $taken ?: [0])
            ->where('total', '>', 0)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'total']);

        if ($orders->isEmpty()) {
            $this->warn('Không còn đơn hàng nào chưa gắn conversion — bỏ qua bước tạo conversion.');

            return ['payable_count' => 0, 'payable_sum' => 0, 'held_count' => 0, 'pending_count' => 0, 'rejected_count' => 0];
        }

        $min = (int) getConfigDb('config_affiliate_min_payout');
        $hold = max(0, (int) getConfigDb('config_affiliate_hold_days'));

        $payableCount = 0;
        $payableSum   = 0;
        $held = $pending = $rejected = 0;

        foreach ($orders as $order) {
            $base       = (int) round((float) $order->total);
            $commission = (int) round($base * $rate / 100);
            if ($commission <= 0) {
                continue;
            }

            // 1) Ưu tiên gom đủ ngưỡng bằng các đơn Approved đã qua hold.
            if ($payableSum < $min || $payableCount < 2) {
                $this->makeConversion($affiliate, $order, $base, $commission, $rate, [
                    'status'      => AffiliateConversionStatus::Approved,
                    'approved_at' => now()->subDays($hold + 3),
                    'created_at'  => now()->subDays($hold + 10),
                ]);
                $payableCount++;
                $payableSum += $commission;
                continue;
            }

            // 2) Một đơn Approved nhưng MỚI duyệt hôm qua — chốt kỳ phải bỏ qua.
            if ($held === 0) {
                $this->makeConversion($affiliate, $order, $base, $commission, $rate, [
                    'status'      => AffiliateConversionStatus::Approved,
                    'approved_at' => now()->subDay(),
                    'created_at'  => now()->subDays(3),
                ]);
                $held++;
                continue;
            }

            // 3) Đơn chưa giao xong.
            if ($pending === 0) {
                $this->makeConversion($affiliate, $order, $base, $commission, $rate, [
                    'status'      => AffiliateConversionStatus::Pending,
                    'approved_at' => null,
                    'created_at'  => now()->subDay(),
                ]);
                $pending++;
                continue;
            }

            // 4) Đơn đã huỷ.
            if ($rejected === 0) {
                $this->makeConversion($affiliate, $order, $base, $commission, $rate, [
                    'status'      => AffiliateConversionStatus::Rejected,
                    'approved_at' => null,
                    'created_at'  => now()->subDays(5),
                ]);
                $rejected++;
                continue;
            }

            break;
        }

        return [
            'payable_count'  => $payableCount,
            'payable_sum'    => $payableSum,
            'held_count'     => $held,
            'pending_count'  => $pending,
            'rejected_count' => $rejected,
        ];
    }

    private function makeConversion(
        Affiliate $affiliate,
        Orders $order,
        int $base,
        int $commission,
        float $rate,
        array $meta,
    ): void {
        // Insert THÔ chứ không AffiliateConversion::create(): model bật
        // $timestamps nên Eloquent sẽ ghi đè created_at/updated_at bằng
        // now() — mà cả bộ demo này dựa vào việc created_at/approved_at nằm
        // đúng chỗ trong quá khứ (qua hoặc chưa qua hold_days).
        DB::table('affiliate_conversion')->insert([
            'affiliate_id'    => $affiliate->id,
            'order_id'        => $order->id,
            'click_id'        => null,
            'coupon_code'     => null,
            'order_total'     => $base,
            'commission'      => $commission,
            'commission_rate' => $rate,
            'status'          => $meta['status']->value,
            'approved_at'     => $meta['approved_at'],
            'created_at'      => $meta['created_at'],
            'updated_at'      => $meta['created_at'],
        ]);

        // Query-builder update: KHÔNG fire observer trên `orders` (luồng thật
        // cũng làm vậy — xem AffiliateConversionService). Seed mà bắn observer
        // thì OrderRewardObserver/audit log sẽ ghi nhầm một loạt sự kiện.
        DB::table('orders')->where('id', $order->id)->update(['affiliate_id' => $affiliate->id]);
    }

    // ===================== DỌN =====================

    private function clean(): int
    {
        $email = (string) $this->option('email');

        $user = User::withTrashed()->where('email', $email)->first();
        if (! $user) {
            $this->info('Không tìm thấy user demo (' . $email . ') — không có gì để dọn.');

            return self::SUCCESS;
        }

        $affiliate = Affiliate::where('user_id', $user->id)->first();

        if ($affiliate) {
            $id = (int) $affiliate->id;

            // Trả orders.affiliate_id về NULL TRƯỚC khi xoá: FK
            // fk_orders_affiliate là nullOnDelete nên DB cũng tự làm, nhưng
            // làm tường minh thì log dọn dẹp đọc được, không phải suy ra.
            $orders = DB::table('orders')->where('affiliate_id', $id)->update(['affiliate_id' => null]);

            $conversions = AffiliateConversion::where('affiliate_id', $id)->delete();
            $clicks      = AffiliateClick::where('affiliate_id', $id)->delete();
            $links       = AffiliateLink::where('affiliate_id', $id)->delete();
            $payouts     = DB::table('affiliate_payout')->where('affiliate_id', $id)->delete();
            $coupons     = DB::table('affiliate_coupon')->where('affiliate_id', $id)->delete();

            $affiliate->delete();

            $this->info("Đã xoá affiliate #{$id}: {$conversions} conversion, {$clicks} click, {$links} link, {$payouts} kỳ chi trả, {$coupons} coupon map; {$orders} đơn trả affiliate_id về NULL.");
        }

        // forceDelete: User dùng SoftDeletes, delete() thường chỉ đánh dấu
        // deleted_at → lần seed sau `where('email')` vẫn thấy row cũ và không
        // dọn sạch được thật.
        $user->forceDelete();
        $this->info('Đã xoá user demo: ' . $email);

        return self::SUCCESS;
    }
}
