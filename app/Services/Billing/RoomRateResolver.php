<?php

namespace App\Services\Billing;

use App\Models\Room;
use App\Models\Booking;

class RoomRateResolver
{
    public function nightly(Room $room, Booking $booking): float
    {
        $effectiveAmount = app(BookingService::class)->effectiveBookingAmount($booking);

        $nights = max(
            $booking->check_in && $booking->check_out
                ? $booking->check_in->diffInDays($booking->check_out)
                : 1,
            1
        );

        $roomCount = max((int) ($booking->quantity ?: $booking->rooms->count() ?: 1), 1);

        return round($effectiveAmount / ($nights * $roomCount), 2);
    }
}