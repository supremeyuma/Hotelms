<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PosDocketItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'docket_id',
        'menu_item_id',
        'item_name',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function docket()
    {
        return $this->belongsTo(PosDocket::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
