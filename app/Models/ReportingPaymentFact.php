<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingPaymentFact extends Model
{
    protected $table = 'reporting_payment_facts';
    protected $fillable = [
        'payment_id',
        'booking_id',
        'room_id',
        'payment_method',
        'provider',
        'transaction_id',
        'collected_by_id',
        'paid_at',
        'amount',
        'currency',
        'status',
        'charges_applied',
        'charges_count',
        'overpayment_flag',
        'overpayment_amount',
        'reversed',
        'reversed_at',
        'reversal_reason',
        'receipt_number',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'overpayment_flag' => 'boolean',
            'reversed' => 'boolean',
            'meta' => 'json',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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
