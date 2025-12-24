<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomPayment extends Model
{
    protected $fillable = [
        'booking_id',
        'room_id',
        'amount',
        'method',
        'notes'
    ];

    public function room() { return $this->belongsTo(Room::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
}
