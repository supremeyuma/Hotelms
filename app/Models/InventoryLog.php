<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'staff_id',
        'change',
        'type',
        'meta'  => 'array',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /* ---------------- Relationships ---------------- */

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
        // Laravel will automatically use inventory_item_id ✅
    }
}
