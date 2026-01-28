<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CleaningInventoryTemplate extends Model
{
    protected $fillable = [
        'room_type_id',
        'inventory_item_id',
        'quantity',
    ];

    /* ============================
     * RELATIONSHIPS
     * ============================ */

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
