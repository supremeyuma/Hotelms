<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'room_type_id',
        'room_number',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}
