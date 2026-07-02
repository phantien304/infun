<?php

namespace App\Mail\Web;

use App\Mail\Base;

class JobMailer extends Base
{
    public function verifyEmail($email, $data)
    {
        $from = getModuleConfig('job_mailer.verify_email.from');
        $sender = getModuleConfig('job_mailer.verify_email.sender');
        $subject = trans('mailer.verify_email.subject');
        $content = '';
        $cc = [];
        $contentHtml = view('web::mailer.verify_email', compact('email', 'data'));
        return $this->sendMail($from, $sender, $email, $subject, $content, $cc, $contentHtml);
    }

    public function authenticatedEmail($email)
    {
        $from = getModuleConfig('job_mailer.verify_email.from');
        $sender = getModuleConfig('job_mailer.verify_email.sender');
        $subject = trans('mailer.authenticated_email.subject');
        $content = '';
        $cc = [];
        $contentHtml = view('web::mailer.authenticated_email', compact('email'));
        return $this->sendMail($from, $sender, $email, $subject, $content, $cc, $contentHtml);
    }

    public function forgotPassword($email, $data)
    {
        $from = getModuleConfig('job_mailer.forgot_password.from');
        $sender = getModuleConfig('job_mailer.forgot_password.sender');
        $subject = trans('mailer.forgot_password.subject');
        $content = '';
        $cc = [];
        $contentHtml = view('web::mailer.forgot_password', compact('email', 'data'));
        return $this->sendMail($from, $sender, $email, $subject, $content, $cc, $contentHtml);
    }

    public function orderCreate($data)
    {
        $infoCustomer = $data[2];
        $invoice = getConfigDb('config_invoice_prefix') . '-' . $infoCustomer['uniqid'];
        $from = getModuleConfig('job_mailer.order_create.from');
        $sender = getModuleConfig('job_mailer.order_create.sender');
        $subject = sprintf(trans('mailer.order_create.subject'), getConfigDb('config_name'), $invoice);
        $content = '';
        $cc = [];
        $contentHtml = view('web::mailer.order_create', compact('data'));
        return $this->sendMail($from, $sender, $infoCustomer['email'], $subject, $content, $cc, $contentHtml);
    }

    public function orderCreateToAdmin($data)
    {
        $from = getModuleConfig('job_mailer.order_create.from');
        $sender = getModuleConfig('job_mailer.order_create.sender');
        $subject = sprintf(trans('mailer.order_create.subject_to_admin'), getConfigDb('config_name'));
        $content = '';
        $cc = [];
        $contentHtml = view('web::mailer.order_create_to_admin', compact('data'));
        return $this->sendMail($from, $sender, getConfigDb('config_email_notification'), $subject, $content, $cc, $contentHtml);
    }

    public function contactCreateToAdmin($data)
    {
        $from = getModuleConfig('job_mailer.contact_create.from');
        $sender = getModuleConfig('job_mailer.contact_create.sender');
        $subject = sprintf(trans('mailer.contact_create.subject_to_admin'), getConfigDb('config_name'));
        $content = '';
        $cc = [];
        $contentHtml = view('web::mailer.contact_create_to_admin', compact('data'));
        return $this->sendMail($from, $sender, getConfigDb('config_email_notification'), $subject, $content, $cc, $contentHtml);
    }

    public function consultSignToCustomer()
    {

    }

    public function consultSignToAdmin($data)
    {
        $from = getModuleConfig('job_mailer.consult_sign.from');
        $sender = getModuleConfig('job_mailer.consult_sign.sender');
        $subject = sprintf(trans('mailer.consult_sign.subject_to_admin'), getConfigDb('config_name'));
        $content = '';
        $cc = [];
        $contentHtml = view('web::mailer.consult_sign_to_admin', compact('data'));
        return $this->sendMail($from, $sender, getConfigDb('config_email_notification'), $subject, $content, $cc, $contentHtml);
    }
}
