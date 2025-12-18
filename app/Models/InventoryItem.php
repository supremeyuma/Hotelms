<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'quantity',
        'unit',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function logs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('quantity', '<=', $threshold);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

}
