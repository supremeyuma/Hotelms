<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingEvent extends Model
{
    protected $table = 'reporting_events';
    protected $fillable = [
        'occurred_at',
        'event_type',
        'domain',
        'department',
        'room_id',
        'booking_id',
        'guest_id',
        'user_id',
        'reference_type',
        'reference_id',
        'status_before',
        'status_after',
        'amount',
        'currency',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'json',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
