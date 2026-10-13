<?php

namespace App\Models;

use App\Sync\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PosDocketItem extends Model
{
    use HasUlids, Syncable;

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
        return $this->belongsTo(PosDocket::class, 'docket_id');
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
