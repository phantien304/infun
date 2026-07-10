<?php

namespace App\Enums;

/**
 * Trạng thái phiếu chuyển kho (stock_transfer.status).
 * Luồng: Draft → InTransit → Received; Draft/InTransit → Cancelled.
 */
enum StockTransferStatus: string
{
    case Draft     = 'draft';
    case InTransit = 'in_transit';
    case Received  = 'received';
    case Cancelled = 'cancelled';

    /** Trạng thái còn cho phép hủy. */
    public function cancellable(): bool
    {
        return $this === self::Draft || $this === self::InTransit;
    }

    /** Trạng thái chốt sổ — không sửa được item nữa. */
    public function finalized(): bool
    {
        return $this === self::Received || $this === self::Cancelled;
    }
}
