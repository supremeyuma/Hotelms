<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingLaundryFact extends Model
{
    protected $table = 'reporting_laundry_facts';
    protected $fillable = [
        'laundry_order_id',
        'room_id',
        'booking_id',
        'created_at',
        'pickup_at',
        'processing_started_at',
        'ready_at',
        'delivered_at',
        'cancelled_at',
        'item_count',
        'total_amount',
        'payment_status',
        'was_refunded',
        'completion_minutes',
        'delay_minutes',
        'sla_breach',
        'status',
        'complaint_count',
        'reopen_count',
        'dispute_images',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'pickup_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'was_refunded' => 'boolean',
            'sla_breach' => 'boolean',
            'dispute_images' => 'json',
        ];
    }

    public function laundryOrder(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
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
