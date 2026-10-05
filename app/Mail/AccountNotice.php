<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $headline,
        public string $bodyText,
        public ?string $greeting = null,
        public ?string $actionUrl = null,
        public ?string $actionText = null,
        public array $details = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-notice');
    }
}
