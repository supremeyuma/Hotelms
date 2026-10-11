<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildInventoryStocks extends Command
{
    protected $signature = "inventory:rebuild";

    protected $description = "Rebuild inventory stock balances from movements";

    public function handle(): int
    {
        $this->info("Rebuilding inventory stocks...");
        DB::transaction(function () {
            InventoryStock::query()->delete();
            $items = InventoryItem::all();
            $locations = InventoryLocation::all();
            foreach ($items as $item) {
                foreach ($locations as $location) {
                    // Single quotes, not double: double quotes are identifiers
                    // in standard SQL and only happen to work on SQLite. The
                    // signed SUM is order-independent, so it needs no explicit
                    // total order to reach an identical balance on every node
                    // (spec section 6, Class 3).
                    $total = InventoryMovement::where("inventory_item_id", $item->id)
                        ->where("inventory_location_id", $location->id)
                        ->sum(DB::raw("CASE WHEN type IN ('in', 'transfer_in') THEN quantity WHEN type IN ('out', 'transfer_out', 'wastage') THEN -quantity ELSE quantity END"));
                    InventoryStock::updateOrCreate(
                        ["inventory_item_id" => $item->id, "inventory_location_id" => $location->id],
                        ["quantity" => $total ?? 0]
                    );
                }
            }
        });
        $this->info("Done.");
        return self::SUCCESS;
    }
}
