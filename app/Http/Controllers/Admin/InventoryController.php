<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Exceptions\InsufficientInventoryException;

class InventoryController extends Controller
{
    protected InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->middleware(['auth', 'role:manager|md']);
        $this->inventory = $inventory;
    }

    /* =====================================================
     * INDEX
     * ===================================================== */

    public function index()
    {
        $items = InventoryItem::with('stocks.location')
            ->paginate(25)
            ->through(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'total_stock' => $item->totalStock(),
                'low_stock' => $item->isLowStock(),
                'stocks' => $item->stocks->map(fn ($s) => [
                    'location_id' => $s->location->id,
                    'location' => $s->location->name,
                    'quantity' => $s->quantity,
                ])
            ]);

        $locations = InventoryLocation::all(['id', 'name']);

        return Inertia::render('Admin/Inventory/Index', [
            'items' => $items,
            'locations' => $locations,
        ]);
    }

    /* =====================================================
     * CREATE
     * ===================================================== */

    public function create()
    {
        return Inertia::render('Admin/Inventory/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'sku' => 'required|string|unique:inventory_items,sku',
            'unit' => 'nullable|string|max:50',
            'low_stock_threshold' => 'required|integer|min:0',
            'meta' => 'nullable|array',
        ]);

        $this->inventory->createItem($data);

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', 'Inventory item created.');
    }

    /* =====================================================
     * EDIT
     * ===================================================== */

    public function edit(InventoryItem $inventory)
    {
        return Inertia::render('Admin/Inventory/Edit', [
            'item' => $inventory
        ]);
    }

    public function update(Request $request, InventoryItem $inventory)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'sku' => 'required|string|unique:inventory_items,sku,' . $inventory->id,
            'unit' => 'nullable|string|max:50',
            'low_stock_threshold' => 'required|integer|min:0',
            'meta' => 'nullable|array',
        ]);

        $this->inventory->updateItem($inventory, $data);

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', 'Inventory item updated.');
    }

    /* =====================================================
     * SHOW
     * ===================================================== */

    public function show(InventoryItem $inventory)
    {
        $inventory->load([
            'stocks.location',
            'movements.staff'
        ]);

        return Inertia::render('Admin/Inventory/Show', [
            'item' => [
                'id' => $inventory->id,
                'name' => $inventory->name,
                'sku' => $inventory->sku,
                'unit' => $inventory->unit,
                'updated_at' => $inventory->updated_at,
                'total_stock' => $inventory->totalStock(),
                'low_stock' => $inventory->isLowStock(),
                'stocks' => $inventory->stocks->map(fn ($s) => [
                    'location_id' => $s->location->id,
                    'location' => $s->location->name,
                    'quantity' => $s->quantity,
                ]),
                'movements' => $inventory->movements
                    ->sortByDesc('created_at')
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'type' => $m->type,
                        'quantity' => $m->quantity,
                        'reason' => $m->reason,
                        'location' => $m->location->name ?? null,
                        'staff' => $m->staff,
                        'created_at' => $m->created_at,
                    ])
            ]
        ]);
    }

    /* =====================================================
     * STOCK OPERATIONS
     * ===================================================== */

    public function addStock(Request $request, $inventoryId)
    {

        
        $data = $request->validate([
            'inventory_location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);
        
        // explicit lookup (will throw 404 if not found)
        $inventory = InventoryItem::findOrFail($inventoryId);

        

        $location = InventoryLocation::findOrFail($data['inventory_location_id']);

        $this->inventory->addStock(
            item: $inventory,
            location: $location,
            quantity: $data['quantity'],
            staffId: $request->user()->id,
            reason: $data['reason']
        );

        return back()->with('success', 'Stock added.');
    }

    public function useItem(Request $request, $inventoryId)
    {
        $data = $request->validate([
            'inventory_location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        try {

            // explicit lookup (will throw 404 if not found)
            $inventory = InventoryItem::findOrFail($inventoryId);

            $location = InventoryLocation::findOrFail($data['inventory_location_id']);

            $this->inventory->consumeStock(
                item: $inventory,
                location: $location,
                quantity: $data['quantity'],
                staffId: $request->user()->id,
                reason: $data['reason']
            );

            return back()->with('success', 'Inventory consumed.');
        }
        catch (InsufficientInventoryException $e) {

            return back()->withErrors([
                'quantity' => "Only {$e->available} item(s) available in this location."
            ])->withInput();
        }
    }
}
