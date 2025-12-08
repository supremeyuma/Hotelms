<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'location',
        'amenities',
    ];

    protected $casts = [
        'amenities' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    // A property has many room types
    public function roomTypes()
    {
        return $this->hasMany(RoomType::class);
    }

    // A property has many rooms
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    // A property has many bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeLocatedAt($query, $location)
    {
        return $query->where('location', 'LIKE', "%{$location}%");
    }
}
