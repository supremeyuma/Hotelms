<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EventTicketPurchaseConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $event;
    public $qrCodeUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($ticket, $event, $qrCodeUrl)
    {
        $this->ticket = $ticket;
        $this->event = $event;
        $this->qrCodeUrl = $qrCodeUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🎫 Your Ticket for {$this->event->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.event-ticket-confirmation',
            with: [
                'ticket' => $this->ticket,
                'event' => $this->event,
                'qrCodeUrl' => $this->qrCodeUrl,
                'totalAmount' => $this->ticket->amount_paid * $this->ticket->quantity,
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
                ->as('ticket-qr-code.png')
                ->withMime('image/png'),
        ];
    }
}