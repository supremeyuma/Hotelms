<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'room_id', 'amount'
    ];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function room() { return $this->belongsTo(Room::class); }
}
