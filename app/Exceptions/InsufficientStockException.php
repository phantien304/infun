<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném ra khi một dòng đơn hàng cố trừ vượt tồn thực tế (on_hand) đối với
 * variant có inventory_policy = DENY.
 *
 * Vì CreateOrderService::create() bọc toàn bộ trong DB::transaction, việc ném
 * exception này sẽ ROLLBACK cả đơn — không có đơn nào được tạo và on_hand
 * không bị đẩy xuống âm. Đây là chốt chặn oversell cuối cùng, hoạt động ngay
 * cả khi lớp kiểm tra tồn ở cart/reservation bị vượt qua do race condition.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productVariantId,
        public readonly int $requested,
        public readonly int $available,
        string $message = '',
    ) {
        parent::__construct(
            $message !== ''
                ? $message
                : sprintf(
                    'Insufficient stock for variant %d: requested %d, available %d',
                    $productVariantId,
                    $requested,
                    $available,
                ),
        );
    }
}
