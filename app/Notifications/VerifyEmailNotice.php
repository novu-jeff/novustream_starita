<?php

namespace App\Notifications;

use App\Mail\AccountNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotice extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): AccountNotice
    {
        $name = trim((string) ($notifiable->name ?? ''));

        return (new AccountNotice(
            'Verify your email address',
            "Please confirm that this email address belongs to you. We use it for your Sta. Rita Water District account, including bills, payments, and application updates.\n\nThis link expires in 60 minutes. If you did not create an account, you can ignore this message.",
            $name !== '' ? 'Hello '.$name.',' : 'Hello,',
            $this->verificationUrl($notifiable),
            'Verify email address',
        ))->to($notifiable->email);
    }

    protected function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
