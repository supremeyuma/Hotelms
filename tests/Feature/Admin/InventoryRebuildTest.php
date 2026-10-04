<?php

namespace Tests\Feature\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InventoryRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_produces_same_quantities_as_incremental(): void
    {
        $service = $this->app->make(InventoryService::class);

        $mainStore = InventoryLocation::create([
            "name" => "Main Store",
            "type" => InventoryLocation::TYPE_MAIN_STORE,
        ]);

        $kitchen = InventoryLocation::create([
            "name" => "Kitchen",
            "type" => InventoryLocation::TYPE_KITCHEN,
        ]);

        $item = InventoryItem::create([
            "name" => "Rice",
            "sku" => "INV-RICE-002",
            "unit" => "kg",
            "low_stock_threshold" => 2.5,
        ]);

        // Ensure stock rows exist
        if (InventoryStock::where("inventory_item_id", $item->id)->count() == 0) {
            InventoryStock::create(["inventory_item_id" => $item->id, "inventory_location_id" => $mainStore->id, "quantity" => 0]);
            InventoryStock::create(["inventory_item_id" => $item->id, "inventory_location_id" => $kitchen->id, "quantity" => 0]);
        }

        $service->addStock($item, $mainStore, 20, null, "Initial");
        $service->transferStock($item, $mainStore, $kitchen, 5);
        $service->consumeStock($item, $kitchen, 2, null, "Used");

        $beforeMain = (float) InventoryStock::where("inventory_item_id", $item->id)->where("inventory_location_id", $mainStore->id)->value("quantity");
        $beforeKitchen = (float) InventoryStock::where("inventory_item_id", $item->id)->where("inventory_location_id", $kitchen->id)->value("quantity");

        Artisan::call("inventory:rebuild");

        $afterMain = (float) InventoryStock::where("inventory_item_id", $item->id)->where("inventory_location_id", $mainStore->id)->value("quantity");
        $afterKitchen = (float) InventoryStock::where("inventory_item_id", $item->id)->where("inventory_location_id", $kitchen->id)->value("quantity");

        $this->assertEquals($beforeMain, $afterMain);
        $this->assertEquals($beforeKitchen, $afterKitchen);
    }
}
