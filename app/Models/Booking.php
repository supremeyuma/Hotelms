<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id',
        'room_id',
        'user_id',
        'booking_code',
        'check_in',
        'check_out',
        'guests',
        'total_amount',
        'status',
        'details',
        'room_type_id',
        'adults',
        'children',
        'special_requests',
        'guest_email',
        'guest_phone',
        'expires_at',
        'guest_name',
        'quantity',
    ];

    protected $casts = [
        'details' => 'array',
        'check_in' => 'date',
        'check_out' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /* ---------------- Relationships ---------------- */

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('check_in', '>', now());
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_rooms');
    }
    public function roomType()
{
    return $this->belongsTo(RoomType::class);
}


}
