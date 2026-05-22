<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\Client\InfunStudio\ContactSendEmailToAdminJob;
use App\Repositories\Client\InfunStudio\ContactRepository;

class ContactController extends Controller
{
    public function __construct(ContactRepository $contactRepository)
    {
        parent::__construct();
        $this->setRepository($contactRepository);
        $this->_breadcrumbs = [
            ['text' => trans('messages.breadcrumbs.home'), 'href' => '/', 'separator' => false],
            ['text' => trans('messages.breadcrumbs.contact'), 'href' => route('contact.index'), 'separator' => false],
        ];
    }

    public function index()
    {
        $this->_processMetaSeo('_buildForSeoBySetting', 'seo_title_contacts', 'seo_description_contacts');

        return $this->render('client.infunstudio.contact.index');
    }

    public function send()
    {
        $params = $this->getParams();
        $validator = $this->getRepository()->getValidator();
        if (!$validator->validateCreate($params)) {
            return errValidator($validator->errorsBag()->getMessages(), 200);
        }

        if ($this->getRepository()->saveContact($params)) {
            if (filled(getConfigDb('config_email_notification'))) {
                $this->_sendNotificationToAdmin($params);
            }
            return successNoData('Gửi liên hệ thành công');
        }
        return errNoValidator('Gửi liên hệ thất bại');
    }

    protected function _sendNotificationToAdmin($params)
    {
        dispatch(new ContactSendEmailToAdminJob($params));
    }
}
