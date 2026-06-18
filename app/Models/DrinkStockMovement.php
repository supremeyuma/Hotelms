<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class DrinkStockMovement extends Model
{
    use HasUlids;

    protected $table = 'drink_stock_movements';

    protected $fillable = [
        'drink_stock_id',
        'type',
        'quantity_change',
        'full_bottles_before',
        'full_bottles_after',
        'reference_type',
        'reference_id',
        'staff_id',
        'notes',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'full_bottles_before' => 'integer',
        'full_bottles_after' => 'integer',
    ];

    public function drinkStock()
    {
        return $this->belongsTo(DrinkStock::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
