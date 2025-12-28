<?php
// app/Models/LaundryOrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryOrderItem extends Model
{
    protected $fillable = [
        'laundry_order_id',
        'laundry_item_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }

    public function item()
    {
        return $this->belongsTo(LaundryItem::class, 'laundry_item_id');
    }
}
