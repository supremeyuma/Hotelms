<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BookingResolverService
{
    /**
     * Resolve the currently checked-in booking for a room
     */
    public function resolveActiveBookingForRoom(int $roomId): Booking
    {
        $booking = Booking::where('status', 'checked_in')
            ->whereHas('rooms', fn ($q) => $q->where('rooms.id', $roomId))
            ->first();

        if (!$booking) {
            throw new ModelNotFoundException(
                "No active booking found for room ID {$roomId}"
            );
        }

        return $booking;
    }
}
