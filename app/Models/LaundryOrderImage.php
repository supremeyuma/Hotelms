<?php
// app/Models/LaundryOrderImage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryOrderImage extends Model
{
    protected $fillable = [
        'laundry_order_id',
        'path',
    ];

    public function order()
    {
        return $this->belongsTo(LaundryOrder::class, 'laundry_order_id');
    }
}
