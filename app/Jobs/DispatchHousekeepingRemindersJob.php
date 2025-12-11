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
 * DispatchHousekeepingRemindersJob
 *
 * Sends reminders to housekeeping for checkouts today or rooms needing attention.
 */
class DispatchHousekeepingRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function tags(): array
    {
        return ['housekeeping','reminder'];
    }

    public function handle(NotificationService $notifier, AuditLoggerService $audit)
    {
        // Bookings checking out today
        $today = today();
        $checkouts = Booking::whereDate('check_out', $today)->whereIn('status', ['checked_in','booked'])->get();

        foreach ($checkouts as $booking) {
            $notifier->notifyDepartment('housekeeping', "Room {$booking->room_id} has checkout today", ['booking_id' => $booking->id]);
        }

        $audit->log('housekeeping_reminders_dispatched', 'Booking', null, ['count' => $checkouts->count()]);
    }
}
