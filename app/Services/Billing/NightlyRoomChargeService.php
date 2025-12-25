<?php

namespace App\Services\Billing;

use App\Models\Room;
use App\Models\Charge;
use Carbon\Carbon;
use App\Events\RoomBillingUpdated;
use App\Models\Booking;

class NightlyRoomChargeService
{
    public function charge(Room $room, Booking $booking, Carbon $date): void
    {
        $exists = Charge::where('room_id', $room->id)
            ->where('type', 'nightly')
            ->whereDate('created_at', $date)
            ->exists();

        if ($exists) {
            return;
        }

        $amount = app(RoomRateResolver::class)
        ->nightly($room, $booking);

        Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'type' => 'nightly',
            'description' => 'Nightly room charge',
            'amount' => $amount,
        ]);

        event(new RoomBillingUpdated($room));
    }
}
