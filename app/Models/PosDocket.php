<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PosDocket extends Model
{
    use HasUlids;

    protected $fillable = [
        'device_id',
        'session_id',
        'staff_id',
        'docket_number',
        'table_identifier',
        'customer_name',
        'booking_id',
        'room_id',
        'status',
        'subtotal',
        'vat',
        'service_charge',
        'discount_amount',
        'total',
        'void_reason',
        'closed_by',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'vat' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function device()
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function session()
    {
        return $this->belongsTo(PosSession::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function items()
    {
        return $this->hasMany(PosDocketItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PosDocketPayment::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeForStaff($query, $staffId)
    {
        return $query->where('staff_id', $staffId);
    }
}
