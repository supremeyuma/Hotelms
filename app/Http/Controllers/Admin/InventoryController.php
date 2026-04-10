<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
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
            ->withSum('stocks as total_stock_sum', 'quantity')
            ->paginate(25)
            ->through(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'low_stock_threshold' => (float) $item->low_stock_threshold,
                'total_stock' => $item->totalStock(),
                'low_stock' => $item->isLowStock(),
                'stocks' => $item->stocks->map(fn ($s) => [
                    'location_id' => $s->location->id,
                    'location' => $s->location->name,
                    'quantity' => (float) $s->quantity,
                ])
            ]);

        $locations = InventoryLocation::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

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
            'low_stock_threshold' => 'required|numeric|min:0',
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
            'low_stock_threshold' => 'required|numeric|min:0',
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
            'movements.staff',
            'movements.location',
        ]);

        return Inertia::render('Admin/Inventory/Show', [
            'item' => [
                'id' => $inventory->id,
                'name' => $inventory->name,
                'sku' => $inventory->sku,
                'unit' => $inventory->unit,
                'low_stock_threshold' => (float) $inventory->low_stock_threshold,
                'updated_at' => $inventory->updated_at,
                'total_stock' => $inventory->totalStock(),
                'low_stock' => $inventory->isLowStock(),
                'stocks' => $inventory->stocks->map(fn ($s) => [
                    'location_id' => $s->location->id,
                    'location' => $s->location->name,
                    'quantity' => (float) $s->quantity,
                ]),
                'movements' => $inventory->movements
                    ->sortByDesc('created_at')
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'type' => $m->type,
                        'quantity' => (float) $m->quantity,
                        'reason' => $m->reason,
                        'location' => $m->location->name ?? null,
                        'staff' => $m->staff,
                        'created_at' => $m->created_at,
                        'meta' => $m->meta,
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
            'quantity' => 'required|numeric|gt:0',
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
            reason: $data['reason'] ?: 'Manual stock addition'
        );

        return back()->with('success', 'Stock added.');
    }

    public function useItem(Request $request, $inventoryId)
    {
        $data = $request->validate([
            'inventory_location_id' => 'required|exists:inventory_locations,id',
            'quantity' => 'required|numeric|gt:0',
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
                reason: $data['reason'] ?: 'Manual stock usage'
            );

            return back()->with('success', 'Inventory consumed.');
        }
        catch (InsufficientInventoryException $e) {

            return back()->withErrors([
                'quantity' => "Only {$e->available} item(s) available in this location."
            ])->withInput();
        }
    }

    public function transfer(Request $request, InventoryItem $inventory)
    {
        $data = $request->validate([
            'from_inventory_location_id' => 'required|exists:inventory_locations,id',
            'to_inventory_location_id' => 'required|exists:inventory_locations,id|different:from_inventory_location_id',
            'quantity' => 'required|numeric|gt:0',
            'reason' => 'required|string|max:255',
        ]);

        try {
            $this->inventory->transferStock(
                item: $inventory,
                from: InventoryLocation::findOrFail($data['from_inventory_location_id']),
                to: InventoryLocation::findOrFail($data['to_inventory_location_id']),
                quantity: (float) $data['quantity'],
                staffId: $request->user()->id,
                reason: $data['reason']
            );

            return back()->with('success', 'Stock transferred.');
        } catch (InsufficientInventoryException $e) {
            return back()->withErrors([
                'transfer_quantity' => "Only {$e->available} item(s) available in the source location."
            ])->withInput();
        }
    }

    public function reconcile(Request $request, InventoryItem $inventory)
    {
        $data = $request->validate([
            'inventory_location_id' => 'required|exists:inventory_locations,id',
            'actual_quantity' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        $this->inventory->reconcileStock(
            item: $inventory,
            location: InventoryLocation::findOrFail($data['inventory_location_id']),
            actualQuantity: (float) $data['actual_quantity'],
            staffId: $request->user()->id,
            note: $data['reason']
        );

        return back()->with('success', 'Stock reconciled.');
    }
}
