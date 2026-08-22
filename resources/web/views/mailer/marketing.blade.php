{{--
    Mail marketing hàng loạt.

    `$messageHtml` in KHÔNG escape — nội dung do admin soạn trong TinyMCE ở
    CMS, escape thì HTML sẽ hiện ra dạng chữ. Quyền tạo chiến dịch
    (create-mail-campaign) là ranh giới tin cậy, không phải chỗ này.

    Chân mail BẮT BUỘC có link huỷ nhận tin: thiếu nó thì mail hàng loạt bị
    các nhà cung cấp coi là spam, và khách không có cách nào thoát ngoài việc
    bấm "báo cáo spam" — thứ làm hỏng uy tín gửi của cả tên miền.
--}}
{!! $messageHtml !!}
<br>
<hr>
<p style="font-size:12px;color:#888;">
    {!! sprintf(trans('mailer.marketing.footer'), getConfigDb('config_name')) !!}<br>
    <a href="{{ $unsubscribeUrl }}">{{ trans('mailer.marketing.unsubscribe') }}</a>
</p>
