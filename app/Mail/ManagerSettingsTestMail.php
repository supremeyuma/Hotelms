<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerSettingsTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $hotelName,
        public string $activeMailer,
        public string $sentBy,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->hotelName} mail settings test",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.manager-settings-test-mail',
        );
    }
}
