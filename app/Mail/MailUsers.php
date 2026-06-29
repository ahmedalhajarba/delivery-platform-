<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

// تم تعطيل كلاس الإيميلات بناءً على طلب الإدارة
// class MailUsers extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $address = 'no-reply@maysanexpress.sa';
        $name = 'Pickto';
        $subject = ' عدنا من جديد ميسان /بيكتو ' ;
        return $this->view('mails.tousers')
                    ->from($address, $name)
                    // ->cc($address, $name)
                    // ->bcc($address, $name)
                    // ->replyTo($address, $name)
                    ->subject($subject)
                    ->with([ 'mobile' => $this->data ]);
    }
}