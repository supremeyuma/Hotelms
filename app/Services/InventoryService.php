<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function createItem(array $data): InventoryItem
    {
        return InventoryItem::create($data);
    }

    public function updateItem(InventoryItem $item, array $data)
    {
        $oldQty = $item->quantity;

        $item->update($data);

        // Automatic inventory log
        InventoryLog::create([
            'inventory_item_id' => $item->id,
            'old_quantity' => $oldQty,
            'new_quantity' => $item->quantity,
            'changed_by'  => auth()->id(),
        ]);
    }
}
