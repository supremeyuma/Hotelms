<?php

namespace App\Services\Billing;

use App\Models\Room;
use App\Models\Charge;
use Carbon\Carbon;
use App\Events\RoomBillingUpdated;

class NightlyRoomChargeService
{
    public function charge(Room $room, int $bookingId, Carbon $date): void
    {
        $exists = Charge::where('room_id', $room->id)
            ->where('type', 'nightly')
            ->whereDate('created_at', $date)
            ->exists();

        if ($exists) {
            return;
        }

        $amount = $room->roomType->base_price;

        Charge::create([
            'booking_id' => $bookingId,
            'room_id' => $room->id,
            'type' => 'nightly',
            'description' => 'Nightly room charge',
            'amount' => $amount,
        ]);

        event(new RoomBillingUpdated($room));
    }
}
