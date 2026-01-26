<?php
// app/Models/InventoryItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'unit',
        'meta',
        'low_stock_threshold'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function stocks()
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function totalStock(): int
    {
        return (int) $this->stocks()->sum('quantity');
    }

    public function isLowStock(): bool
    {
        return $this->totalStock() <= $this->low_stock_threshold;
    }
}
