<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $fillable = [
        'booking_id',
        'room_id',
        'total_charges',
        'total_payments',
        'balance',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
