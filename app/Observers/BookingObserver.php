<?php

// app/Observers/BookingObserver.php
namespace App\Observers;

use App\Models\Booking;
use App\Models\RoomCleaning;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    public function checkedOut(Booking $booking)
    {
        RoomCleaning::create([
            'room_id' => $booking->room_id,
            'status' => 'dirty'
        ]);
    }

    public function updated(Booking $booking)
    {
        if ($booking->isDirty('status') && in_array($booking->status, ['active', 'checked_in'], true)) {
            $booking->generateRoomAccessTokens();
        }
    }

}
