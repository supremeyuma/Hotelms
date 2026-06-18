<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class DrinkStock extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'drink_stock';

    protected $fillable = [
        'menu_item_id',
        'full_bottles',
        'opened_bottles',
        'pours_per_bottle',
        'low_stock_threshold',
        'is_active',
    ];

    protected $casts = [
        'full_bottles' => 'integer',
        'opened_bottles' => 'integer',
        'pours_per_bottle' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function movements()
    {
        return $this->hasMany(DrinkStockMovement::class);
    }

    public function getEstimatedPoursRemainingAttribute(): int
    {
        return ($this->full_bottles * $this->pours_per_bottle) + $this->opened_bottles;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->full_bottles <= $this->low_stock_threshold;
    }
}
