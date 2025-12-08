<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class BookingService
{
    /**
     * Create a new booking with room assignment.
     */
    public function createBooking(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            $booking = Booking::create($data);

            // Assign rooms (many-to-many)
            if (!empty($data['room_ids'])) {
                $booking->rooms()->sync($data['room_ids']);
            }

            return $booking;
        });
    }

    public function updateBooking(Booking $booking, array $data)
    {
        $booking->update($data);

        if (!empty($data['room_ids'])) {
            $booking->rooms()->sync($data['room_ids']);
        }
    }

    public function cancelBooking(Booking $booking)
    {
        $booking->update(['status' => 'cancelled']);
    }
}
