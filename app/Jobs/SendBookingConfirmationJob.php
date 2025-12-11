<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\NotificationService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SendBookingConfirmationJob
 *
 * Sends confirmation to guest via email/SMS and logs audit.
 */
class SendBookingConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Booking $booking;
    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking->withoutRelations();
    }

    public function tags(): array
    {
        return ['booking','confirmation','booking:'.$this->booking->id];
    }

    public function handle(NotificationService $notifier, AuditLoggerService $audit)
    {
        $booking = Booking::with('user','room')->find($this->booking->id);
        if (! $booking) return;

        $subject = "Booking Confirmation - {$booking->booking_code}";
        $message = "Dear {$booking->user->name}, your booking is confirmed for {$booking->check_in} to {$booking->check_out}.";

        // send via service (can handle email + sms)
        $notifier->notifyStaff(null, $subject, ['booking_id' => $booking->id]); // internal note
        // For guest, it might send email via SendGuestMessageJob or direct:
        SendGuestMessageJob::dispatch($booking->user->email, $subject, $message);

        $audit->log('booking_confirmation_sent', $booking, $booking->id, ['to' => $booking->user_id]);
    }
}
