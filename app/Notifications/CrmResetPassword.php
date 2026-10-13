<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CrmResetPassword extends Notification
{
    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $frontend = rtrim((string) env('CRM_FRONTEND_URL', config('app.url')), '/');
        $url = $frontend . '/crm/reset-password?token=' . urlencode($this->token) . '&email=' . urlencode($email);

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line('You are receiving this email because we received a password reset request for your CRM account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in ' . config('auth.passwords.crm_users.expire', 60) . ' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
