<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class Base extends Mailable
{
    use Queueable;
    use SerializesModels;

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

    protected function sendMail($from, $sender, $to, $subject, $content, $cc = [], $contentHtml = '', $bcc = [])
    {
        if ($this->getSendType() !== 'send') {
            // @todo send_later — đẩy vào queue thay vì gửi ngay.
            return $this;
        }

        $html = $contentHtml instanceof \Illuminate\Contracts\Support\Renderable
            ? $contentHtml->render()
            : (string) $contentHtml;

        Mail::html($html, function ($message) use ($from, $sender, $to, $subject, $content, $cc, $bcc) {
            $message->to($to)
                ->subject($subject)
                ->from($from, $sender);

            if (! empty($cc)) {
                $message->cc($cc);
            }
            if (! empty($bcc)) {
                $message->bcc($bcc);
            }
            if (filled($content)) {
                $message->text($content);
            }
        });

        return $this;
    }
}
