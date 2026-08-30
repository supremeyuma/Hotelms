<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $hotelName,
        public string $name,
        public string $email,
        public string $subject,
        public string $message,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New contact message from {$this->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-submission-mail',
        );
    }
}
