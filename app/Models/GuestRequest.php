<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GuestRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requestable_type',
        'requestable_id',
        'booking_id',
        'room_id',
        'type',
        'status',
        'notes',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function isFrontDeskVisible(): bool
    {
        return match ($this->type) {
            'laundry' => $this->status === 'requested',
            'cleaning' => in_array($this->status, ['requested']),
            'kitchen', 'bar' => in_array($this->status, ['pending']),
            default => false,
        };
    }

    public function requestable(): MorphTo
{
    return $this->morphTo();
}
}
