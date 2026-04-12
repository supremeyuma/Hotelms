<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\RoomCheckoutService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * AutomaticCheckoutJob
 *
 * Automatically checks out guests at midday (12:00 PM) on their checkout date.
 * This ensures rooms are released for cleaning and new check-ins by the standard checkout time.
 */
class AutomaticCheckoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function tags(): array
    {
        return ['bookings', 'checkout', 'auto'];
    }

    public function handle(RoomCheckoutService $checkoutService, AuditLoggerService $audit): void
    {
        $today = now()->toDateString();

        // Get all bookings that should be checked out today
        // Status must be 'checked_in' and check_out date must be today
        $bookings = Booking::where('check_out', $today)
            ->where('status', 'checked_in')
            ->with('rooms')
            ->get();

        if ($bookings->isEmpty()) {
            Log::info('AutomaticCheckoutJob: No bookings to checkout today');
            return;
        }

        foreach ($bookings as $booking) {
            try {
                // Checkout each room in the booking
                foreach ($booking->rooms as $room) {
                    $checkoutService->checkoutRoom($booking, $room);
                    
                    $audit->log(
                        'booking_auto_checked_out',
                        $booking,
                        $booking->id,
                        [
                            'room_id' => $room->id,
                            'room_name' => $room->name,
                            'guest' => $booking->guest_name
                        ]
                    );
                }

                // Update booking status to checked_out
                $booking->update(['status' => 'checked_out']);

                Log::info("AutomaticCheckoutJob: Booking {$booking->booking_code} checked out successfully");
            } catch (Exception $e) {
                Log::error("AutomaticCheckoutJob: Failed to checkout booking {$booking->booking_code}", [
                    'error' => $e->getMessage(),
                    'booking_id' => $booking->id
                ]);
            }
        }
    }
}
