<?php

namespace App\Http\Controllers\ClubPos;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use App\Services\DrinkStockService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private DrinkStockService $stockService,
    ) {}

    public function index()
    {
        $items = MenuItem::where('service_area', 'bar')
            ->where('is_available', true)
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                $stock = DrinkStock::where('menu_item_id', $item->id)->first();
                return [
                    'menu_item' => $item,
                    'stock' => $stock,
                    'low_stock' => $stock ? $stock->full_bottles <= $stock->low_stock_threshold : true,
                ];
            });

        return response()->json($items);
    }

    public function add(Request $request, MenuItem $menuItem)
    {
        $validated = $request->validate([
            'bottles' => 'required|integer|min:1|max:9999',
            'notes' => 'nullable|string|max:500',
        ]);

        $stock = $this->stockService->addStock(
            $menuItem,
            $validated['bottles'],
            $request->user()->id,
            $validated['notes'] ?? null,
        );

        return response()->json($stock);
    }

    public function stocktake(Request $request, MenuItem $menuItem)
    {
        $validated = $request->validate([
            'actual_bottles' => 'required|integer|min:0|max:9999',
            'notes' => 'nullable|string|max:500',
        ]);

        $stock = $this->stockService->stocktake(
            $menuItem,
            $validated['actual_bottles'],
            $request->user()->id,
            $validated['notes'] ?? null,
        );

        return response()->json($stock);
    }
}
