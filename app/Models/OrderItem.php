<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'item_name',
        'qty',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /* ---------------- Relationships ---------------- */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
