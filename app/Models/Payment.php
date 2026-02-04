<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'room_id',
        'amount',
        'amount_paid',
        'currency',
        'reference',
        'status',
        'provider',
        'external_reference',
        'verified_at',
        'payment_type',
        'payment_reference',
        'flutterwave_tx_ref',
        'flutterwave_tx_id',
        'flutterwave_tx_status',
        'flutterwave_refund_id',
        'idempotency_key',
        'raw_response',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
