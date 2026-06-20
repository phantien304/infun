<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class Base extends Mailable
{
    use Queueable, SerializesModels;

    protected $_sendType = 'send';

    /**
     * @return string
     */
    public function getSendType()
    {
        return $this->_sendType;
    }

    /**
     * @param string $sendType
     */
    public function setSendType($sendType)
    {
        $this->_sendType = $sendType;
    }

    protected function _send($from, $sender, $to, $subject, $content, $cc = [], $contentHtml = '', $bcc = [])
    {
        if ($this->getSendType() !== 'send') {
            // @todo send_later — đẩy vào queue thay vì gửi ngay.
            return $this;
        }

        // Swift Mailer đã bị gỡ từ Laravel 9 (dự án đang Laravel 12 + Symfony
        // Mailer). Gửi HTML qua Mail::html(); thêm phần text thuần nếu có.
        $html = $contentHtml instanceof \Illuminate\Contracts\Support\Renderable
            ? $contentHtml->render()
            : (string) $contentHtml;

        Mail::html($html, function ($message) use ($from, $sender, $to, $subject, $content, $cc, $bcc) {
            $message->to($to)
                ->subject($subject)
                ->from($from, $sender); // address and name

            if (! empty($cc)) {
                $message->cc($cc);
            }
            if (! empty($bcc)) {
                $message->bcc($bcc);
            }
            if (filled($content)) {
                // Illuminate\Mail\Message::__call → Symfony\Component\Mime\Email::text()
                $message->text($content);
            }
        });

        return $this;
    }
}
