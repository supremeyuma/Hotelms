<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BookingRoom extends Pivot
{
    protected $table = 'booking_rooms';

    protected $casts = [
        'checked_in_at'  => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     |  State helpers
     | -----------------------------------------------------------------
     */

    public function isCheckedIn(): bool
    {
        return ! is_null($this->checked_in_at) && is_null($this->checked_out_at);
    }

    public function isCheckedOut(): bool
    {
        return ! is_null($this->checked_out_at);
    }

    public function isActive(): bool
    {
        return $this->isCheckedIn() && ! $this->isCheckedOut();
    }

    /* -----------------------------------------------------------------
     |  Lifecycle actions
     | -----------------------------------------------------------------
     */

    public function checkIn(?Carbon $time = null): void
    {
        if ($this->checked_in_at) {
            return;
        }

        $this->update([
            'checked_in_at' => $time ?? now(),
        ]);
    }

    public function checkOut(?Carbon $time = null): void
    {
        if ($this->checked_out_at) {
            return;
        }

        $this->update([
            'checked_out_at' => $time ?? now(),
        ]);

        // Revoke room access immediately
        RoomAccessToken::where('booking_id', $this->booking_id)
            ->where('room_id', $this->room_id)
            ->delete();

        // Mark room as available again
        $this->room?->update([
            'status' => 'available',
        ]);
    }

    /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
