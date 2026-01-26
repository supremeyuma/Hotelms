<?php
// app/Models/InventoryMovement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
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

}
