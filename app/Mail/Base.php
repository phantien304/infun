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
        $sendType = $this->getSendType();
        if ($sendType == 'send') {
            Mail::send([], [], function ($message) use ($from, $sender, $to, $subject, $content, $cc, $contentHtml, $bcc) {
                $message->to($to)->subject($subject);
                $message->cc($cc);
                $message->bcc($bcc);
                $message->from($from, $sender); // address and name
                $message->setContentType("text/plain");
                $message->setBody($content)->setCharset('utf8')->setEncoder(new \Swift_Mime_ContentEncoder_PlainContentEncoder('7bit'));
                if (!empty($contentHtml)) {
                    $message->addPart($contentHtml, 'text/html');
                }
            });
        } elseif ($sendType == 'send_later') {
            //@todo send mail later
        }

        return $this;
    }
}
