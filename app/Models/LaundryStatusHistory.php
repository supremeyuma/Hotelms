<?php
// app/Models/LaundryStatusHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaundryStatusHistory extends Model
{
    protected $fillable = [
        'laundry_order_id',
        'from_status',
        'to_status',
        'changed_by',
    ];

    public function order()
    {
        return $this->belongsTo(LaundryOrder::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
