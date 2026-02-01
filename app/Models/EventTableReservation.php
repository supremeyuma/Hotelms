<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventTableReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'table_number',
        'number_of_guests',
        'amount',
        'base_amount',
        'vat_amount',
        'service_charge_amount',
        'payment_method',
        'payment_reference',
        'payment_status',
        'status',
        'qr_code',
        'special_requests',
        'checked_in_at',
        'refund_requested_at',
        'refunded_at',
        'notes',
    ];

    protected $casts = [
        'number_of_guests' => 'integer',
        'amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'refund_requested_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function getFormattedAmountAttribute()
    {
        return '₦' . number_format($this->amount_paid, 2);
    }

    public function getFormattedReservationDateAttribute()
    {
        return $this->created_at->format('M j, Y - g:i A');
    }

    public function getCanBeCheckedInAttribute()
    {
        return $this->status === 'confirmed' && 
               $this->payment_status === 'paid' && 
               !$this->checked_in_at;
    }

    public function getIsRefundableAttribute()
    {
        $refundDeadline = $this->event->ticket_sales_end->copy()->addHours(24);
        return $this->status === 'confirmed' && 
               now()->lt($refundDeadline) && 
               !$this->refunded_at &&
               !$this->checked_in_at;
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }
}