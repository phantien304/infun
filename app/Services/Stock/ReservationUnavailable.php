<?php

namespace App\Services\Stock;

use RuntimeException;

/**
 * Exception nội bộ của StockService — chỉ dùng để rollback transaction
 * reserveCart khi một dòng không đủ tồn để giữ chỗ. Không rò rỉ ra ngoài
 * service (reserveCart bắt lại và trả về mảng ['ok'=>false, ...]).
 *
 * @internal
 */
class ReservationUnavailable extends RuntimeException
{
    /** @param array{name:string, available:int, requested:int} $info */
    public function __construct(public readonly array $info)
    {
        parent::__construct('Reservation unavailable');
    }
}
