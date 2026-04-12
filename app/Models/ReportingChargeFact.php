<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingChargeFact extends Model
{
    protected $table = 'reporting_charge_facts';
    protected $fillable = [
        'charge_id',
        'booking_id',
        'room_id',
        'charge_type',
        'chargeble_id',
        'chargeable_type',
        'posted_by_id',
        'posted_at',
        'amount',
        'currency',
        'status',
        'payment_applied',
        'outstanding',
        'refunded',
        'refund_amount',
        'refunded_at',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refunded' => 'boolean',
            'meta' => 'json',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
