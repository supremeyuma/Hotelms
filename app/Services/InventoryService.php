<?php
// app/Services/InventoryService.php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Services\AuditLoggerService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * InventoryService
 *
 * CRUD and stock operations for inventory items, including low stock alerts.
 */
class InventoryService
{
    protected AuditLoggerService $audit;

    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Create inventory item.
     *
     * @param array $data
     * @return InventoryItem
     */
    public function createItem(array $data): InventoryItem
    {
        $item = InventoryItem::create($data);
        $this->audit->log('inventory_item_created', $item, $item->id, ['data' => $data]);
        return $item;
    }

    /**
     * Update inventory item.
     *
     * @param InventoryItem $item
     * @param array $data
     * @return InventoryItem
     */
    public function updateItem(InventoryItem $item, array $data): InventoryItem
    {
        $before = $item->toArray();
        $item->update($data);
        $this->audit->logChange('inventory_item_updated', $item, $before, $item->toArray());
        return $item;
    }

    /**
     * Deduct stock and create log.
     *
     * @param int $itemId
     * @param int $quantity
     * @param int|null $staffId
     * @return InventoryLog
     * @throws Exception
     */
    public function deductStock(int $itemId, int $quantity, ?int $staffId = null): InventoryLog
    {
        return DB::transaction(function () use ($itemId, $quantity, $staffId) {
            $item = InventoryItem::lockForUpdate()->find($itemId);
            if (! $item) throw new Exception('Inventory item not found.');

            $before = $item->quantity;
            $item->decrement('quantity', $quantity);
            $item->refresh();

            $log = InventoryLog::create([
                'inventory_item_id' => $item->id,
                'staff_id' => $staffId,
                'change' => -abs($quantity),
                'meta' => ['before' => $before, 'after' => $item->quantity]
            ]);

            $this->audit->log('inventory_stock_deducted', $item, $item->id, ['change' => $quantity, 'staff' => $staffId]);

            return $log;
        });
    }

    /**
     * Add stock
     *
     * @param int $itemId
     * @param int $quantity
     * @param int|null $staffId
     * @return InventoryLog
     */
    public function addStock(int $itemId, int $quantity, ?int $staffId = null): InventoryLog
    {
        return DB::transaction(function () use ($itemId, $quantity, $staffId) {
            $item = InventoryItem::lockForUpdate()->findOrFail($itemId);
            $before = $item->quantity;
            $item->increment('quantity', $quantity);
            $item->refresh();

            $log = InventoryLog::create([
                'inventory_item_id' => $item->id,
                'staff_id' => $staffId,
                'change' => abs($quantity),
                'meta' => ['before' => $before, 'after' => $item->quantity]
            ]);

            $this->audit->log('inventory_stock_added', $item, $item->id, ['change' => $quantity, 'staff' => $staffId]);

            return $log;
        });
    }

    /**
     * Low stock items
     *
     * @param int $threshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function lowStock(int $threshold = 10)
    {
        return InventoryItem::where('quantity', '<=', $threshold)->get();
    }

    /**
     * Paginated list
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function list(int $perPage = 25): LengthAwarePaginator
    {
        return InventoryItem::latest()->paginate($perPage);
    }
}
