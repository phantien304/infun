<?php

namespace App\Enums;

/**
 * Tập người nhận của một chiến dịch mail.
 *
 * Giữ nguyên chuỗi của mt219 (`newsletter`, `user_all`, `user_group`, `user`,
 * `file`) — đổi tên sẽ làm log cũ và tài liệu vận hành nói khác nhau, đổi lại
 * chẳng được gì.
 */
enum MailCampaignSendTo: string
{
    case Newsletter = 'newsletter'; // khách đã bật nhận tin (user.newsletter = 1)
    case AllUsers   = 'user_all';   // mọi khách có email
    case UserGroup  = 'user_group'; // theo nhóm khách
    case Users      = 'user';       // chọn tay từng khách
    case File       = 'file';       // upload file danh sách email (mỗi dòng 1 email)

    public static function fromInput(mixed $value): ?self
    {
        return $value instanceof self ? $value : self::tryFrom((string) $value);
    }
}
