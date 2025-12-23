<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RoomAccessToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'room_id',
        'token',
        'expires_at'
    ];

    protected $dates = [
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            // other casts...
        ];
    }

    public static function generate(int $bookingId, int $roomId, ?\DateTime $expiresAt = null): self
    {
        return self::create([
            'booking_id' => $bookingId,
            'room_id' => $roomId,
            'token' => hash('sha256', Str::random(64)),
            'expires_at' => $expiresAt,
        ]);
    }

    public function isValid(): bool
    {
        return !$this->expires_at || $this->expires_at->isFuture();
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
