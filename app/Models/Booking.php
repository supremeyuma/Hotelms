<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Carbon\Carbon;
use App\Models\RoomAccessToken;

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
        'payment_method',
        'payment_status',
        'details',
        'nightly_rate',
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
        'nightly_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = ['checked_in_rooms_count'];

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
        return $query->whereIn('status', ['active', 'checked_in']);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('check_in', '>', now());
    }

    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'booking_rooms')
        ->using(BookingRoom::class)
        ->withPivot('checked_in_at', 'checked_out_at', 'rate_override', 'booking_rooms.status')
        ->withTimestamps();
    }
    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function roomAccessToken()
    {
        return $this->hasOne(RoomAccessToken::class);
    }

      public function generateRoomAccessTokens(): void
    {
        // Remove any previous tokens for this booking
        RoomAccessToken::where('booking_id', $this->id)->delete();

        $expiresAt = Carbon::parse($this->check_out)->addHours(1); // 1-hour grace

        // Make sure rooms are loaded
        $this->load('rooms');

        foreach ($this->rooms as $room) {

            if (!$room->id) {
                throw new \Exception("Room ID is null for booking {$this->id}");
            }
            RoomAccessToken::create([
                'booking_id' => $this->id,
                'room_id' => $room->id,
                'token' => hash('sha256', \Illuminate\Support\Str::random(64)),
                'expires_at' => $expiresAt,
            ]);
        }
    }

    // Optional helper to get all access tokens
    public function accessTokens()
    {
        return $this->hasMany(RoomAccessToken::class);
    }

     public function getAccessTokenAttribute(): ?string
    {
        return $this->roomAccessToken?->token;
    }

    public function charges()
    {
        return $this->hasMany(Charge::class);
    }

    public function discountRedemption(): MorphOne
    {
        return $this->morphOne(DiscountCodeRedemption::class, 'redeemable');
    }

    public function getCheckedInRoomsCountAttribute(): int
    {
        return $this->rooms()->wherePivotNotNull('checked_in_at')->count();
    }


}
