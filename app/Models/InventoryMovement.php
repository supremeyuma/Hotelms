<?php
// app/Models/InventoryMovement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'inventory_item_id',
        'inventory_location_id',
        'staff_id',
        'type',
        'quantity',
        'reason',
        'reference_type',
        'reference_id',
        'meta'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'meta' => 'array'
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function location()
    {
        return $this->belongsTo(
            InventoryLocation::class,
            'inventory_location_id'
        );
    }

    public function reference()
    {
        return $this->morphTo();
    }

}
