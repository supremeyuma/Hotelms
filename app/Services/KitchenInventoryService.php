<?php

namespace App\Services;

use App\Models\Order;
use App\Models\InventoryLocation;
use App\Models\MenuInventoryRecipe;
use Illuminate\Support\Facades\DB;

class KitchenInventoryService
{
    protected InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    public function consumeForOrder(Order $order): void
    {
        $location = InventoryLocation::where('type', 'kitchen')->firstOrFail();

        DB::transaction(function () use ($order, $location) {
            foreach ($order->items as $item) {
                $recipes = MenuInventoryRecipe::where(
                    'menu_item_id',
                    $item->menu_item_id
                )->get();

                foreach ($recipes as $recipe) {
                    $this->inventory->consumeStock(
                        item: $recipe->inventoryItem,
                        location: $location,
                        quantity: $recipe->quantity * $item->quantity,
                        staffId: $order->handled_by,
                        reason: "Kitchen order #{$order->id}"
                    );
                }
            }
        });
    }
}
