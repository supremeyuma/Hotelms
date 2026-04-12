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

    

    public function guestRequest()
    {
        return $this->morphOne(GuestRequest::class, 'requestable');
    }

    // LaundryOrder.php
    // app/Models/Order.php
    public function charge()
    {
        return $this->morphOne(Charge::class, 'billable');
    }


}
