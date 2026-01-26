<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;

class InventoryStockSeeder extends Seeder
{
    public function run(): void
    {
        InventoryItem::each(function ($item) {
            InventoryLocation::each(function ($location) use ($item) {
                InventoryStock::firstOrCreate([
                    'inventory_item_id' => $item->id,
                    'inventory_location_id' => $location->id,
                ], [
                    'quantity' => 0,
                ]);
            });
        });
    }
}
