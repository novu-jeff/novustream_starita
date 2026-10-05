<?php

namespace App\Notifications;

use App\Mail\AccountNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

class ResetPasswordNotice extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AccountNotice
    {
        $expire = (int) Config::get('auth.passwords.users.expire', 60);
        $name = trim((string) ($notifiable->name ?? ''));

        return (new AccountNotice(
            'Reset your password',
            "We received a request to reset the password for your Sta. Rita Water District account.\n\nThis link expires in {$expire} minutes. If you did not ask for a reset, you can ignore this email. Your password will stay the same.",
            $name !== '' ? 'Hello '.$name.',' : 'Hello,',
            url(route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false)),
            'Reset password',
        ))->to($notifiable->email);
    }
}
