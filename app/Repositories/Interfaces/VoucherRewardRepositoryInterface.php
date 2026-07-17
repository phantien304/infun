<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\VoucherRewardGrant;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface VoucherRewardRepositoryInterface extends BaseRepositoryInterface
{
    /** Rule active + trong khung ngày, sort_order tăng dần. */
    public function listRunningRules(): Collection;

    /**
     * Chiếm 1 suất quota — conditional UPDATE (pattern gift.used_count):
     * chỉ tăng khi quota_total NULL hoặc granted_count + by <= quota_total.
     * Trả affected rows: 0 = hết quota đúng lúc chốt (đơn khác nhanh hơn).
     */
    public function incrementGrantedCount(int $ruleId, int $by = 1): int;

    /** Trả suất khi grant fail/revoke — không âm quá 0. */
    public function decrementGrantedCount(int $ruleId, int $by = 1): void;

    /** Đơn này đã được rule này thưởng chưa (check rẻ trước khi vào transaction). */
    public function grantExists(int $ruleId, int $orderId): bool;

    /** Đếm số lần user (theo user_id, fallback email) đã nhận thưởng của rule — so max_per_user. */
    public function countGrantsForUser(int $ruleId, ?int $userId, string $email): int;

    public function createGrant(array $data): VoucherRewardGrant;

    /** Toàn bộ grant của 1 đơn (kèm voucher) — dùng khi revoke lúc hủy đơn. */
    public function grantsForOrder(int $orderId): Collection;

    public function flushCache(): void;
}
