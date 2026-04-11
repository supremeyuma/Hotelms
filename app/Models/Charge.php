<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Charge extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'room_id', 'description', 'amount', 'status', 'payment_mode',
         'charge_date', 'type', 'billable_id', 'billable_type',
    ];

    public function booking() { return $this->belongsTo(Booking::class); }
    public function room() { return $this->belongsTo(Room::class); }

    public function billable()
    {
        return $this->morphTo();
    }

    public function discountRedemption(): MorphOne
    {
        return $this->morphOne(DiscountCodeRedemption::class, 'redeemable');
    }
}
