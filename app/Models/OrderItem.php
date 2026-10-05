<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'item_name',
        'qty',
        'price',
        'note',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Line total is derived; order_items stores unit price and quantity only.
     */
    protected function subtotal(): Attribute
    {
        return Attribute::get(
            fn () => round((float) $this->price * (int) $this->qty, 2)
        );
    }

    /* ---------------- Relationships ---------------- */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

}
