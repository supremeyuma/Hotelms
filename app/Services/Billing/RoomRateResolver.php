<?php

namespace App\Services\Billing;

use App\Models\Room;
use App\Models\Booking;

class RoomRateResolver
{
    public function nightly(Room $room, Booking $booking): float
    {
        $pivot = $booking->rooms()
            ->where('rooms.id', $room->id)
            ->first()
            ->pivot;

        return $pivot->rate_override
            ?? $room->roomType->base_price;
    }
}
