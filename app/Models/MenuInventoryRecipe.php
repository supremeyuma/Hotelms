<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuInventoryRecipe extends Model
{
    protected $fillable = [
        'menu_item_id',
        'inventory_item_id',
        'quantity',
    ];

    /* ===============================
     * RELATIONSHIPS
     * =============================== */

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
