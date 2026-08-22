<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\CustomerRepositoryInterface;
use Illuminate\Http\Request;

/**
 * Huỷ nhận email marketing từ link trong mail.
 *
 * Route gắn middleware `signed` — chữ ký của Laravel đã chống việc sửa
 * `?email=` trên URL để tắt nhận tin của người khác, nên không cần thêm cột
 * token riêng trên bảng `user`.
 *
 * KHÔNG yêu cầu đăng nhập: bắt người ta đăng nhập mới huỷ được là cách nhanh
 * nhất để họ bấm "báo cáo spam" thay vì huỷ — thứ làm hỏng uy tín gửi của cả
 * tên miền.
 */
class NewsletterController extends Controller
{
    public function __construct(
        protected CustomerRepositoryInterface $customerRepo,
    ) {
    }

    public function unsubscribe(Request $request)
    {
        $email = trim((string) $request->query('email'));

        if ($email !== '') {
            $this->customerRepo->optOutNewsletterByEmail($email);
        }

        // Luôn trả cùng một trang dù email có tồn tại hay không — không tiết
        // lộ "địa chỉ này có tài khoản ở đây".
        return response()
            ->view('web::newsletter.unsubscribed')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
