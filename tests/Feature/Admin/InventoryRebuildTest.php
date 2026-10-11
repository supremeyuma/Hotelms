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

    public function test_rebuild_is_order_independent(): void
    {
        $location = InventoryLocation::create([
            "name" => "Main Store",
            "type" => InventoryLocation::TYPE_MAIN_STORE,
        ]);

        $first = InventoryItem::create(["name" => "Rice", "sku" => "INV-ORD-1", "unit" => "kg"]);
        $second = InventoryItem::create(["name" => "Beans", "sku" => "INV-ORD-2", "unit" => "kg"]);

        // The same multiset of movements, recorded in two different orders.
        $this->movement($first, $location, "in", 20);
        $this->movement($first, $location, "out", 5);
        $this->movement($first, $location, "in", 3);

        $this->movement($second, $location, "in", 3);
        $this->movement($second, $location, "in", 20);
        $this->movement($second, $location, "out", 5);

        Artisan::call("inventory:rebuild");

        $firstTotal = (float) InventoryStock::where("inventory_item_id", $first->id)->where("inventory_location_id", $location->id)->value("quantity");
        $secondTotal = (float) InventoryStock::where("inventory_item_id", $second->id)->where("inventory_location_id", $location->id)->value("quantity");

        $this->assertSame(18.0, $firstTotal);
        $this->assertSame($firstTotal, $secondTotal);
    }

    private function movement(InventoryItem $item, InventoryLocation $location, string $type, float $quantity): void
    {
        InventoryMovement::create([
            "inventory_item_id" => $item->id,
            "inventory_location_id" => $location->id,
            "type" => $type,
            "quantity" => $quantity,
        ]);
    }
}
