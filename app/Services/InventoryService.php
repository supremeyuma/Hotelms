<?php
// app/Services/InventoryService.php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Exceptions\InsufficientInventoryException;

class InventoryService
{
    protected AuditLoggerService $audit;

    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /* =====================================================
     * ITEM MANAGEMENT
     * ===================================================== */

    public function createItem(array $data): InventoryItem
    {
        return DB::transaction(function () use ($data) {
            $item = InventoryItem::create($data);

            foreach (InventoryLocation::all() as $location) {
                InventoryStock::firstOrCreate([
                    'inventory_item_id' => $item->id,
                    'inventory_location_id' => $location->id
                ]);
            }

            $this->audit->log('inventory_item_created', 'InventoryItem', $item->id, $data);

            return $item;
        });
    }

    public function updateItem(InventoryItem $item, array $data): InventoryItem
    {
        $before = $item->toArray();
        $item->update($data);
        $this->audit->logChange('inventory_item_updated', $item, $before, $item->toArray());
        return $item;
    }

    /* =====================================================
     * STOCK MOVEMENTS
     * ===================================================== */

    public function addStock(
        InventoryItem $item,
        InventoryLocation $location,
        int $quantity,
        ?int $staffId = null,
        ?string $reason = null
    ) {

    //dd($item->id);
        return DB::transaction(function () use ($item, $location, $quantity, $staffId, $reason) {

            $stock = InventoryStock::firstOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'inventory_location_id' => $location->id,
                ],
                [
                    'quantity' => 0,
                ]
            );

            $stock->increment('quantity', $quantity);

            InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'staff_id' => $staffId,
                'type' => 'in',
                'quantity' => $quantity,
                'reason' => $reason,
            ]);
        });
    }


    public function consumeStock(
        InventoryItem $item,
        InventoryLocation $location,
        int $quantity,
        ?int $staffId = null,
        ?string $reason = null
    ) {
        return DB::transaction(function () use ($item, $location, $quantity, $staffId, $reason) {

            $stock = InventoryStock::firstOrCreate(
                [
                    'inventory_item_id' => $item->id,
                    'inventory_location_id' => $location->id,
                ],
                [
                    'quantity' => 0,
                ]
            );

            if ($stock->quantity < $quantity) {
                throw new InsufficientInventoryException($stock->quantity);
            }

            $stock->decrement('quantity', $quantity);

            InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'staff_id' => $staffId,
                'type' => 'out',
                'quantity' => $quantity,
                'reason' => $reason,
            ]);
        });
    }

    public function reconcileStock(
        InventoryItem $item,
        InventoryLocation $location,
        int $actualQuantity,
        ?int $staffId,
        string $note
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $actualQuantity, $staffId, $note) {
            $stock = InventoryStock::where([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id
            ])->lockForUpdate()->firstOrFail();

            $difference = $actualQuantity - $stock->quantity;

            $stock->update(['quantity' => $actualQuantity]);

            return InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
                'staff_id' => $staffId,
                'type' => 'adjustment',
                'quantity' => abs($difference),
                'meta' => [
                    'note' => $note,
                    'before' => $stock->quantity,
                    'after' => $actualQuantity
                ]
            ]);
        });
    }
}
