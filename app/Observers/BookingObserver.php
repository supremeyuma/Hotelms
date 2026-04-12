<?php

// app/Observers/BookingObserver.php
namespace App\Observers;

use App\Models\Booking;
use App\Models\RoomCleaning;
use App\Reporting\Projectors\BookingProjector;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    public function created(Booking $booking)
    {
        // Project new booking into reporting layer
        BookingProjector::project($booking);
    }

    public function updated(Booking $booking)
    {
        if ($booking->isDirty('status') && in_array($booking->status, ['active', 'checked_in'], true)) {
            $booking->generateRoomAccessTokens();
        }

        // Track status changes in reporting
        if ($booking->isDirty('status')) {
            BookingProjector::projectOnStatusChange($booking, $booking->getOriginal('status'));
        }

        // Project all updates into reporting layer
        BookingProjector::project($booking);
    }

    public function checkedOut(Booking $booking)
    {
        RoomCleaning::create([
            'room_id' => $booking->room_id,
            'status' => 'dirty'
        ]);

        // Track checkout in reporting
        BookingProjector::projectOnStatusChange($booking, 'active');
    }

}
