<?php

namespace App\Enums;

enum MailCampaignStatus: int
{
    case Queued    = 1; // đã tạo, recipient đã chốt, job đang xếp hàng
    case Sending   = 2; // ít nhất 1 lô đã chạy
    case Completed  = 3; // không còn recipient pending
    case Failed    = 4; // toàn bộ recipient đều lỗi

    public static function fromInput(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_numeric($value) ? self::tryFrom((int) $value) : null;
    }
}
