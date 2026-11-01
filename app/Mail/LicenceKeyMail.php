<?php

namespace App\Mail;

use App\Models\Licence;
use App\Models\LicenceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's durable copy of their licence key.
 *
 * The key is only ever stored as a hash, so this email is the one copy the buyer
 * can come back to. It is sent as soon as the payment completes.
 */
class LicenceKeyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LicenceOrder $order,
        public Licence $licence,
        public string $licenceKey,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your MooreLife licence key — {$this->order->property_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.licence-key',
            with: [
                'order' => $this->order,
                'licence' => $this->licence,
                'licenceKey' => $this->licenceKey,
            ],
        );
    }
}
