<?php

namespace App\Jobs;

use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * SendGuestMessageJob
 *
 * Generic job for sending transactional email messages to guests.
 */
class SendGuestMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $subject;
    public string $body;
    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(string $to, string $subject, string $body)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->body = $body;
    }

    public function tags(): array
    {
        return ['mail','guest','to:'.$this->to];
    }

    public function handle(AuditLoggerService $audit)
    {
        Mail::raw($this->body, function (Message $message) {
            $message->to($this->to)->subject($this->subject);
        });

        $audit->log('guest_message_sent', 'GuestMessage', null, ['to' => $this->to, 'subject' => $this->subject]);
    }
}
