<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Web\ContactSendRequest;
use App\Jobs\ContactSendEmailToAdminJob;
use App\Repositories\Interfaces\ContactRepositoryInterface;

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

    public function send(ContactSendRequest $request): JsonResponse
    {
        $data = $request->validated();

        $contact = $this->contactRepo->saveContact($data);
        if (! $contact) {
            return respondError(trans('messages.contact.send_failed'), 500);
        }

        if (filled(getConfigDb('config_email_notification'))) {
            dispatch(new ContactSendEmailToAdminJob($data));
        }

        return respondMessage(trans('messages.contact.send_success'));
    }
}
