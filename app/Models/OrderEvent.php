<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'staff_id',
        'event',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
