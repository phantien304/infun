<?php

namespace App\Repositories\Eloquent;

use App\Enums\AffiliateStatus;
use App\Models\Entities\Affiliate;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateRepository extends QueryableRepository implements AffiliateRepositoryInterface
{
    public function model(): string
    {
        return Affiliate::class;
    }

    public function findActiveByCode(string $code): ?Affiliate
    {
        if ($code === '') {
            return null;
        }

        return $this->resetModel()
            ->where('code', $code)
            ->where('status', AffiliateStatus::Active->value)
            ->first();
    }

    public function findByUserId(int $userId): ?Affiliate
    {
        if ($userId <= 0) {
            return null;
        }

        return $this->resetModel()->where('user_id', $userId)->first();
    }

    public function findActiveByCouponCode(string $couponCode): ?Affiliate
    {
        if ($couponCode === '') {
            return null;
        }

        return $this->resetModel()
            ->where('status', AffiliateStatus::Active->value)
            ->whereHas('coupons', fn ($q) => $q->where('code', $couponCode))
            ->first();
    }

    public function register(int $userId, array $paymentInfo = []): Affiliate
    {
        $existing = $this->findByUserId($userId);
        if ($existing) {
            return $existing;
        }

        $auto = (int) getConfigDb('config_affiliate_auto_approve') === 1;

        try {
            return Affiliate::create([
                'user_id'      => $userId,
                'code'         => $this->generateUniqueCode(),
                'status'       => ($auto ? AffiliateStatus::Active : AffiliateStatus::Pending)->value,
                'payment_info' => $paymentInfo ?: null,
                'approved_at'  => $auto ? now() : null,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Race double-submit: request song song đã tạo trước → dùng row đó.
            return $this->findByUserId($userId) ?? throw $e;
        }
    }

    /** Mã ref 8 ký tự alphanumeric lowercase, retry khi trùng (xác suất cực thấp). */
    protected function generateUniqueCode(): string
    {
        do {
            $code = strtolower(Str::random(8));
        } while ($this->resetModel()->where('code', $code)->exists());

        return $code;
    }

    // ===================== CMS (admin) =====================

    /**
     * Danh sách KOL cho CMS. Join `user` để tìm theo tên/email — admin gần
     * như luôn tra theo người, không theo mã ref.
     *
     * `affiliate` KHÔNG có soft delete (xoá KOL là xoá cả lịch sử hoa hồng —
     * không cho phép), nên màn này KHÔNG có tham số `deleted_at` như các
     * module khác. Vòng đời quản trị nằm ở `status`: Pending → Active →
     * Suspended.
     */
    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'code', 'clicks_count', 'created_at', 'approved_at'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $keyword  = trim((string) $request->input('keyword', ''));
        $perPage  = max(1, (int) $request->input('per_page', 50));

        $query = $this->resetModel()->newQuery()->with(['user']);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('affiliate.code', 'like', '%' . $keyword . '%')
                    ->orWhereHas('user', function ($u) use ($keyword) {
                        $u->where('full_name', 'like', '%' . $keyword . '%')
                            ->orWhere('email', 'like', '%' . $keyword . '%');
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('affiliate.status', (int) $request->input('status'));
        }

        return $query->orderBy('affiliate.' . $sort, $order)->paginate($perPage);
    }

    public function getForCms(int $id): ?Affiliate
    {
        return $this->resetModel()
            ->with([
                'user',
                'coupons',
                'affiliateLinks' => fn ($q) => $q->orderByDesc('clicks_count')->limit(100),
            ])
            ->find($id);
    }

    /**
     * Admin sửa hồ sơ KOL. CHỈ 3 field: status, commission_rate riêng,
     * payment_info. `code` KHÔNG cho sửa — mã đã nằm trên link/story đã đăng
     * của KOL, đổi là gãy toàn bộ attribution đang chạy. `clicks_count` là
     * aggregate do tracking ghi, cũng không nhận từ form.
     *
     * `approved_at` đóng dấu lần ĐẦU chuyển sang Active và giữ nguyên sau đó
     * — nó là mốc "được duyệt", không phải "lần cuối bật lại".
     */
    public function updateFromCms(Affiliate $affiliate, array $data): Affiliate
    {
        $status = (int) ($data['status'] ?? $affiliate->status);

        $affiliate->status = $status;
        $affiliate->commission_rate = $data['commission_rate'] ?? null;

        if (array_key_exists('payment_info', $data)) {
            $affiliate->payment_info = $data['payment_info'] ?: null;
        }

        if ($status === AffiliateStatus::Active->value && $affiliate->approved_at === null) {
            $affiliate->approved_at = now();
        }

        $affiliate->save();

        return $affiliate->load(['user', 'coupons']);
    }

    /**
     * Gán coupon riêng cho KOL (`affiliate_coupon`) — nguồn attribution thứ
     * hai bên cạnh click, dành cho khách gõ mã từ story/TikTok chứ không bấm
     * link.
     *
     * Một coupon chỉ được thuộc về MỘT KOL: `findActiveByCouponCode()` lấy
     * `first()`, gán 2 KOL cùng một mã thì hoa hồng về tay ai là ngẫu nhiên
     * theo thứ tự row. Nên gán ở đây gỡ luôn coupon đó khỏi KOL khác.
     */
    public function syncCoupons(Affiliate $affiliate, array $couponIds): Affiliate
    {
        $couponIds = array_values(array_unique(array_map('intval', $couponIds)));

        DB::transaction(function () use ($affiliate, $couponIds) {
            if (! empty($couponIds)) {
                DB::table('affiliate_coupon')
                    ->whereIn('coupon_id', $couponIds)
                    ->where('affiliate_id', '!=', $affiliate->id)
                    ->delete();
            }

            $affiliate->coupons()->sync($couponIds);
        });

        return $affiliate->load(['user', 'coupons']);
    }

    /** Đổi trạng thái nhanh từ danh sách (duyệt / tạm khoá). */
    public function setStatus(Affiliate $affiliate, AffiliateStatus $status): Affiliate
    {
        $affiliate->status = $status->value;
        if ($status === AffiliateStatus::Active && $affiliate->approved_at === null) {
            $affiliate->approved_at = now();
        }
        $affiliate->save();

        return $affiliate->load(['user', 'coupons']);
    }

    /**
     * Nạp nhiều KOL kèm user theo id — dùng để dán tên/mã vào kết quả các
     * truy vấn gom nhóm (chốt kỳ, top affiliate) mà không N+1.
     *
     * @return \Illuminate\Support\Collection<int, Affiliate>  keyBy id
     */
    public function findManyWithUser(array $ids): \Illuminate\Support\Collection
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return collect();
        }

        return $this->resetModel()->newQuery()
            ->with('user')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /** Đếm KOL theo trạng thái — thẻ số liệu ở màn báo cáo. */
    public function countByStatus(): array
    {
        // KHÔNG pluck(DB::raw('COUNT(*)'), 'status'): pluck lấy giá trị theo
        // TÊN cột trên row đã fetch, biểu thức thô không có tên → trả rỗng.
        // Đặt alias rồi map tay là cách duy nhất chắc chắn đúng.
        $rows = $this->resetModel()->newQuery()
            ->groupBy('status')
            ->get(['status', DB::raw('COUNT(*) as cnt')]);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->status] = (int) $row->cnt;
        }

        return $out;
    }
}
