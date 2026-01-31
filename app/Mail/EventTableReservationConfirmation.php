<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EventTableReservationConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $event;
    public $qrCodeUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($reservation, $event, $qrCodeUrl)
    {
        $this->reservation = $reservation;
        $this->event = $event;
        $this->qrCodeUrl = $qrCodeUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🍽️ Table Reservation Confirmation for {$this->event->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.event-table-reservation-confirmation',
            with: [
                'reservation' => $this->reservation,
                'event' => $this->event,
                'qrCodeUrl' => $this->qrCodeUrl,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('public', $this->qrCodeUrl)
                ->as('reservation-qr-code.png')
                ->withMime('image/png'),
        ];
    }
}