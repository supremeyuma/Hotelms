<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A contact-form submission, addressed to the hotel.
 *
 * The payload properties are NOT named `subject` / `message`: both are
 * properties on `Mailable`, and redeclaring them here with a type is a PHP
 * fatal ("Type of ...::$subject must not be defined as in class
 * Illuminate\Mail\Mailable"). That fatal meant every contact-form submission
 * died with a 500 before a single email was sent.
 */
class ContactSubmissionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $hotelName,
        public string $name,
        public string $email,
        public string $contactSubject,
        public string $contactMessage,
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
