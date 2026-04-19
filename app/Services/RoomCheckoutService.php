<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Services\Billing\NightlyRoomChargeService;
use Exception;
use Illuminate\Support\Facades\DB;

class RoomCheckoutService
{
    public function checkoutRoom(
        Booking $booking,
        Room $room,
        ?User $by = null
    ) {
        DB::transaction(function () use ($booking, $room, $by) {

// 1. Apply final nightly charges if needed
            app(NightlyRoomChargeService::class)
                ->finalize($booking, $room, false);

            // 2. Enforce room balance = 0
            if (! app(RoomBalanceService::class)
                ->roomCanCheckout($booking, $room)) {

                throw new Exception("Room has outstanding balance.");
            }

            // 3. Update pivot
            $booking->rooms()->updateExistingPivot(
                $room->id,
                [
                    'status' => 'checked_out',
                    'checked_out_at' => now()
                ]
            );

            // 4. Release room
            $room->update(['status' => 'available']);

            // 5. Audit
            app(AuditLoggerService::class)->log(
                'room_checked_out',
                $booking,
                $booking->id,
                [
                    'room_id' => $room->id,
                    'by' => $by?->id
                ]
            );
        });
    }

}
