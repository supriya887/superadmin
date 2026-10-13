<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PartnerOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $expiryMinutes;

    public function __construct($otp, $expiryMinutes = 10)
    {
        $this->otp = $otp;
        $this->expiryMinutes = (int) $expiryMinutes > 0 ? (int) $expiryMinutes : 10;
    }

    public function build()
    {
        return $this->subject('Your One-Time Code - Tizzy Cloud')
            ->view('emails.user_otp')
            ->with([
                'name' => 'there',
                'otp' => $this->otp,
                'expiryMinutes' => $this->expiryMinutes,
                'authContext' => 'sign in',
            ]);
    }
}
