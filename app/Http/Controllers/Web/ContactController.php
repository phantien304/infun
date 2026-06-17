<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ContactSendRequest;
use App\Jobs\ContactSendEmailToAdminJob;
use App\Repositories\Interfaces\ContactRepositoryInterface;

/**
 * Trang liên hệ + endpoint AJAX gửi liên hệ.
 *
 * Refactor sang pattern mới (xem CLAUDE.md "Cũ vs Mới"):
 *  - DI `ContactRepositoryInterface` (auto-bind ở AppServiceProvider).
 *  - `ContactSendRequest` (FormRequest) thay validator legacy
 *    `ContactValidator::validateCreate`. FormRequest tự trả JSON shape AJAX
 *    cũ (`{success:false, message:{field:[...]}}`, HTTP 200) khi fail.
 *  - Job style mới `App\Jobs\ContactSendEmailToAdminJob` (inject JobMailer).
 *  - `processMetaSeo` thay `_processMetaSeo`; URL sinh trực tiếp bằng `route()`.
 */
class ContactController extends Controller
{
    public function __construct(
        protected ContactRepositoryInterface $contactRepo,
    ) {
        $this->breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.contact'), 'href' => route('contact.index'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->processMetaSeo('buildForSeoBySetting', 'seo_title_contacts', 'seo_description_contacts');

        return $this->render('web::contact.index');
    }

    public function send(ContactSendRequest $request)
    {
        $data = $request->validated();

        $contact = $this->contactRepo->saveContact($data);
        if (! $contact) {
            return errNoValidator('Gửi liên hệ thất bại');
        }

        if (filled(getConfigDb('config_email_notification'))) {
            dispatch(new ContactSendEmailToAdminJob($data));
        }

        return successNoData('Gửi liên hệ thành công');
    }
}
