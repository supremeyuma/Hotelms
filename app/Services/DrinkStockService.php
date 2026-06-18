<?php

namespace App\Services;

use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use App\Models\MenuItem;
use App\Models\PosDocket;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\DB;

class DrinkStockService
{
    public function __construct(
        private AuditLoggerService $audit,
    ) {}

    public function getOrCreateForItem(MenuItem $menuItem): DrinkStock
    {
        return DrinkStock::firstOrCreate(
            ['menu_item_id' => $menuItem->id],
            [
                'full_bottles' => 0,
                'opened_bottles' => 0,
                'pours_per_bottle' => 25,
                'low_stock_threshold' => 3,
                'is_active' => true,
            ]
        );
    }

    public function addStock(MenuItem $menuItem, int $bottles, ?int $staffId = null, ?string $notes = null): DrinkStock
    {
        return DB::transaction(function () use ($menuItem, $bottles, $staffId, $notes) {
            $stock = $this->getOrCreateForItem($menuItem);
            $before = $stock->full_bottles;

            $stock->full_bottles += $bottles;
            $stock->save();

            DrinkStockMovement::create([
                'drink_stock_id' => $stock->id,
                'type' => 'delivery',
                'quantity_change' => $bottles,
                'full_bottles_before' => $before,
                'full_bottles_after' => $stock->full_bottles,
                'staff_id' => $staffId,
                'notes' => $notes,
            ]);

            $this->audit->log('drink_stock_added', $stock, $stock->id, [
                'menu_item' => $menuItem->name,
                'bottles_added' => $bottles,
                'total' => $stock->full_bottles,
            ]);

            return $stock;
        });
    }

    public function consumeForSale(MenuItem $menuItem, int $quantity, ?int $staffId = null, ?PosDocket $docket = null): ?DrinkStock
    {
        $stock = DrinkStock::where('menu_item_id', $menuItem->id)->first();
        if (!$stock || !$stock->is_active) {
            return null;
        }

        return DB::transaction(function () use ($stock, $menuItem, $quantity, $staffId, $docket) {
            $before = $stock->full_bottles;

            $consumed = 0;
            $remainingQty = $quantity;

            while ($remainingQty > 0 && $stock->full_bottles > 0) {
                $stock->full_bottles -= 1;
                $consumed += 1;
                $remainingQty -= 1;
            }

            $stock->save();

            if ($consumed > 0) {
                DrinkStockMovement::create([
                    'drink_stock_id' => $stock->id,
                    'type' => 'sale',
                    'quantity_change' => -$consumed,
                    'full_bottles_before' => $before,
                    'full_bottles_after' => $stock->full_bottles,
                    'reference_type' => $docket ? PosDocket::class : null,
                    'reference_id' => $docket?->id,
                    'staff_id' => $staffId,
                ]);
            }

            return $stock->fresh();
        });
    }

    public function stocktake(MenuItem $menuItem, int $actualBottles, ?int $staffId = null, ?string $notes = null): DrinkStock
    {
        return DB::transaction(function () use ($menuItem, $actualBottles, $staffId, $notes) {
            $stock = $this->getOrCreateForItem($menuItem);
            $before = $stock->full_bottles;
            $difference = $actualBottles - $before;

            $stock->full_bottles = $actualBottles;
            $stock->save();

            DrinkStockMovement::create([
                'drink_stock_id' => $stock->id,
                'type' => 'stocktake',
                'quantity_change' => $difference,
                'full_bottles_before' => $before,
                'full_bottles_after' => $actualBottles,
                'staff_id' => $staffId,
                'notes' => $notes ? "Stocktake: {$notes}" : 'Stocktake',
            ]);

            $this->audit->log('drink_stock_stocktake', $stock, $stock->id, [
                'menu_item' => $menuItem->name,
                'before' => $before,
                'after' => $actualBottles,
                'difference' => $difference,
            ]);

            return $stock;
        });
    }

    public function getLowStockItems(): array
    {
        return DrinkStock::where('is_active', true)
            ->whereColumn('full_bottles', '<=', 'low_stock_threshold')
            ->with('menuItem')
            ->get()
            ->toArray();
    }
}
