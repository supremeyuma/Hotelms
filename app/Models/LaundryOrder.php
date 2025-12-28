<?php
// app/Models/LaundryOrder.php

namespace App\Models;

use App\Enums\LaundryStatus;
use Illuminate\Database\Eloquent\Model;

class LaundryOrder extends Model
{
    protected $fillable = [
        'order_code',
        'room_id',
        'status',
        'total_amount',
    ];

    protected $casts = [
        'status' => LaundryStatus::class,
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function items()
    {
        return $this->hasMany(LaundryOrderItem::class);
    }

    public function images()
    {
        return $this->hasMany(LaundryOrderImage::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(LaundryStatusHistory::class);
    }

    /** 
     * Required for Billing & Guest Request linking
     */
    public function billable()
    {
        return $this->morphOne(BillItem::class, 'billable');
    }

    public function guestRequest()
    {
        return $this->morphOne(GuestRequest::class, 'requestable');
    }
}
