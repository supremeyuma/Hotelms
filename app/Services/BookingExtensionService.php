<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;

class BookingExtensionService
{
    public function extendStay(Booking $booking, string $newCheckoutDate): Booking
    {
        $newDate = Carbon::parse($newCheckoutDate);

        if ($newDate->lessThanOrEqualTo(Carbon::parse($booking->check_out))) {
            throw new \Exception("New checkout must be after current checkout.");
        }

        $booking->update(['check_out' => $newDate]);
        return $booking;
    }
}
