<?php

// app/Observers/BookingObserver.php
namespace App\Observers;

use App\Models\Booking;
use App\Models\RoomCleaning;

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
        if ($booking->isDirty('status') && $booking->status === 'active') {
            $booking->generateRoomAccessToken();
        }
    }

}
