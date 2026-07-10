<?php

namespace App\Repositories\Interfaces;

use App\Repositories\Base\BaseRepositoryInterface;

interface UserRewardRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Balance khả dụng: SUM(points) các row status=available và chưa hết hạn
     * (expires_at NULL = vĩnh viễn). Row redeem (âm) luôn được tính.
     */
    public function getTotalPoints(int $userId): int;

    /**
     * Ghi điểm TÍCH khi tạo order — status=pending, chỉ khả dụng sau khi
     * activateOrderReward (đơn giao thành công). Idempotent theo
     * (order_id, transaction_type).
     */
    public function recordOrderReward(int $userId, int $orderId, int $points): void;

    /**
     * Kích hoạt điểm pending của order (đơn giao thành công): status=available,
     * gán expires_at theo config_reward_expiry_months (0 = không hết hạn).
     */
    public function activateOrderReward(int $orderId): void;

    /**
     * Thu hồi điểm tích của order (đơn hủy): status=revoked cho row earn
     * pending/available. Không đụng row redeem.
     */
    public function revokeOrderReward(int $orderId): void;

    /**
     * Ghi điểm TIÊU (row âm, available ngay) khi order dùng điểm.
     * Idempotent theo (order_id, transaction_type).
     */
    public function recordRedeem(int $userId, int $orderId, int $points): void;

    /**
     * Hoàn điểm đã tiêu khi đơn bị hủy (row dương bù lại row redeem).
     * Idempotent — chỉ hoàn nếu có redeem và chưa hoàn.
     */
    public function refundRedeem(int $orderId): void;
}
