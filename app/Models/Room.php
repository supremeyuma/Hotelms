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
        'name',
        'code',
        'display_name',
        'slug',
        'floor',
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

    /*public function bookings()
    {
        return $this->hasMany(Booking::class);
    }*/
    
    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_rooms')
            ->withPivot('checked_in_at', 'checked_out_at', 'rate_override')
            ->withTimestamps();;
    }


     /**
     * Polymorphic images relation
     */
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable')->orderByDesc('is_primary')->orderBy('created_at');
    }

    /**
     * Primary image accessor (returns Image or null)
     */
    public function primaryImage()
    {
        return $this->morphOne(Image::class, 'imageable')->where('is_primary', true);
    }

    /**
     * Helper to get primary image URL or fallback
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        $img = $this->images()->where('is_primary', true)->first();
        if ($img) return $img->url;
        // fallback: first image
        $first = $this->images()->first();
        return $first?->url;
    }

    

    /* ---------------- Scopes ---------------- */

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // app/Models/Room.php
    public function cleanings()
    {
        return $this->hasMany(RoomCleaning::class);
    }

    public function latestCleaning()
    {
        return $this->hasOne(RoomCleaning::class)->latestOfMany();
    }

    public function roomAccessToken()
    {
        return $this->hasOne(RoomAccessToken::class, 'room_id', 'id')
                    ->where('booking_id', request()->booking->id);
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function payments()
    {
        return $this->hasMany(RoomPayment::class);
    }


}
