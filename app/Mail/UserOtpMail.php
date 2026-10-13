<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $name;
    public $expiryMinutes;
    public $authContext;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($otp, $name, $expiryMinutes = 10, $authContext = 'sign in')
    {
        $this->otp = $otp;
        $this->name = $name;
        $this->expiryMinutes = (int) $expiryMinutes > 0 ? (int) $expiryMinutes : 10;
        $this->authContext = trim((string) $authContext) !== '' ? (string) $authContext : 'sign in';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = (string) (DB::connection('pgsql_admin')->table('admin_settings')->where('id', 1)->value('title')
            ?: config('mail.from.name')
            ?: config('app.name')
            ?: 'Tizzy');
        return $this->subject('Your One-Time Code - '.$appName)
            ->view('emails.user_otp')
            ->with([
                'name' => $this->name,
                'otp' => $this->otp,
                'expiryMinutes' => $this->expiryMinutes,
                'authContext' => $this->authContext,
                'appName' => $appName,
            ]);
    }
}
