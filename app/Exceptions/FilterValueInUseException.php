<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Chặn xoá filter_value đang được gán cho sản phẩm — product_filter có FK
 * RESTRICT vào filter_value (database/migrations/2026_06_03_000000_add_constraints_to_product_filter.php),
 * xoá thẳng sẽ vỡ ràng buộc ở tầng DB. FilterController bắt exception này,
 * trả 422 thay vì để lộ SQL exception thô.
 */
class FilterValueInUseException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct(
            $message !== ''
                ? $message
                : 'Không thể xoá: lựa chọn này đang được gán cho sản phẩm.',
        );
    }
}
