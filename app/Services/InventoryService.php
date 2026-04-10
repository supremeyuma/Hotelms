<?php
// app/Services/InventoryService.php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
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

            $this->createMissingStockRowsForItem($item);

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
        float $quantity,
        ?int $staffId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $quantity, $staffId, $reason, $referenceType, $referenceId, $meta) {
            $stock = $this->lockStock($item, $location);
            $before = (float) $stock->quantity;
            $after = round($before + $quantity, 2);

            $stock->update(['quantity' => $after]);

            return $this->recordMovement(
                item: $item,
                location: $location,
                type: InventoryMovement::TYPE_IN,
                quantity: $quantity,
                staffId: $staffId,
                reason: $reason,
                referenceType: $referenceType,
                referenceId: $referenceId,
                meta: array_merge($meta, ['before' => $before, 'after' => $after])
            );
        });
    }


    public function consumeStock(
        InventoryItem $item,
        InventoryLocation $location,
        float $quantity,
        ?int $staffId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $quantity, $staffId, $reason, $referenceType, $referenceId, $meta) {
            $stock = $this->lockStock($item, $location);
            $available = (float) $stock->quantity;

            if ($available < $quantity) {
                throw new InsufficientInventoryException($available);
            }

            $after = round($available - $quantity, 2);
            $stock->update(['quantity' => $after]);

            return $this->recordMovement(
                item: $item,
                location: $location,
                type: InventoryMovement::TYPE_OUT,
                quantity: $quantity,
                staffId: $staffId,
                reason: $reason,
                referenceType: $referenceType,
                referenceId: $referenceId,
                meta: array_merge($meta, ['before' => $available, 'after' => $after])
            );
        });
    }

    public function transferStock(
        InventoryItem $item,
        InventoryLocation $from,
        InventoryLocation $to,
        float $quantity,
        ?int $staffId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): array {
        if ($from->is($to)) {
            throw new \InvalidArgumentException('Transfer locations must be different.');
        }

        return DB::transaction(function () use ($item, $from, $to, $quantity, $staffId, $reason, $referenceType, $referenceId, $meta) {
            $orderedLocations = collect([$from, $to])->sortBy('id')->values();
            $lockedStocks = $orderedLocations->mapWithKeys(
                fn (InventoryLocation $location) => [$location->id => $this->lockStock($item, $location)]
            );

            $fromStock = $lockedStocks[$from->id];
            $toStock = $lockedStocks[$to->id];
            $available = (float) $fromStock->quantity;

            if ($available < $quantity) {
                throw new InsufficientInventoryException($available);
            }

            $fromAfter = round($available - $quantity, 2);
            $toBefore = (float) $toStock->quantity;
            $toAfter = round($toBefore + $quantity, 2);

            $fromStock->update(['quantity' => $fromAfter]);
            $toStock->update(['quantity' => $toAfter]);

            return [
                'out' => $this->recordMovement(
                    item: $item,
                    location: $from,
                    type: InventoryMovement::TYPE_TRANSFER_OUT,
                    quantity: $quantity,
                    staffId: $staffId,
                    reason: $reason,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    meta: array_merge($meta, [
                        'direction' => 'out',
                        'before' => $available,
                        'after' => $fromAfter,
                        'counterparty_location_id' => $to->id,
                    ])
                ),
                'in' => $this->recordMovement(
                    item: $item,
                    location: $to,
                    type: InventoryMovement::TYPE_TRANSFER_IN,
                    quantity: $quantity,
                    staffId: $staffId,
                    reason: $reason,
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    meta: array_merge($meta, [
                        'direction' => 'in',
                        'before' => $toBefore,
                        'after' => $toAfter,
                        'counterparty_location_id' => $from->id,
                    ])
                ),
            ];
        });
    }

    public function reconcileStock(
        InventoryItem $item,
        InventoryLocation $location,
        float $actualQuantity,
        ?int $staffId,
        string $note,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $location, $actualQuantity, $staffId, $note, $referenceType, $referenceId, $meta) {
            $stock = $this->lockStock($item, $location);
            $before = (float) $stock->quantity;
            $difference = round($actualQuantity - $before, 2);

            $stock->update(['quantity' => $actualQuantity]);

            return $this->recordMovement(
                item: $item,
                location: $location,
                type: InventoryMovement::TYPE_ADJUSTMENT,
                quantity: abs($difference),
                staffId: $staffId,
                reason: $note,
                referenceType: $referenceType,
                referenceId: $referenceId,
                meta: array_merge($meta, [
                    'difference' => $difference,
                    'before' => $before,
                    'after' => $actualQuantity,
                ])
            );
        });
    }

    public function createMissingStockRowsForItem(InventoryItem $item): void
    {
        foreach (InventoryLocation::query()->select('id')->get() as $location) {
            InventoryStock::firstOrCreate([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
            ], $this->stockDefaults());
        }
    }

    public function createMissingStockRowsForLocation(InventoryLocation $location): void
    {
        foreach (InventoryItem::query()->select('id')->get() as $item) {
            InventoryStock::firstOrCreate([
                'inventory_item_id' => $item->id,
                'inventory_location_id' => $location->id,
            ], $this->stockDefaults());
        }
    }

    protected function lockStock(InventoryItem $item, InventoryLocation $location): InventoryStock
    {
        InventoryStock::firstOrCreate([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $location->id,
        ], $this->stockDefaults());

        return InventoryStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $location->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function recordMovement(
        InventoryItem $item,
        InventoryLocation $location,
        string $type,
        float $quantity,
        ?int $staffId = null,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = []
    ): InventoryMovement {
        return InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $location->id,
            'staff_id' => $staffId,
            'type' => $type,
            'quantity' => round($quantity, 2),
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'meta' => $meta,
        ]);
    }

    protected function stockDefaults(): array
    {
        $defaults = ['quantity' => 0];

        if (Schema::hasColumn('inventory_stocks', 'type')) {
            $defaults['type'] = InventoryMovement::TYPE_ADJUSTMENT;
        }

        return $defaults;
    }
}
