<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingOrderFact extends Model
{
    protected $table = 'reporting_order_facts';
    protected $fillable = [
        'order_id',
        'service_area',
        'created_at',
        'accepted_at',
        'prepared_at',
        'delivered_at',
        'cancelled_at',
        'room_id',
        'booking_id',
        'staff_owner_id',
        'amount',
        'payment_status',
        'was_refunded',
        'refund_amount',
        'completion_minutes',
        'delay_minutes',
        'delay_severity',
        'status',
        'reopen_count',
        'sla_breached',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'accepted_at' => 'datetime',
            'prepared_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'was_refunded' => 'boolean',
            'sla_breached' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
