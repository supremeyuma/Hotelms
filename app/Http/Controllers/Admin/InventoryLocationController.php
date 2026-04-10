<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryLocationController extends Controller
{
    public function __construct(protected InventoryService $inventory)
    {
        $this->middleware(['auth', 'role:manager|md']);
    }

    /* ================================
     * INDEX
     * ================================ */
    public function index()
    {
        return Inertia::render('Admin/InventoryLocations/Index', [
            'locations' => InventoryLocation::query()
                ->withCount([
                    'stocks as stocked_items_count' => fn ($query) => $query->where('quantity', '>', 0),
                    'movements',
                ])
                ->latest()
                ->paginate(20),
            'types' => InventoryLocation::options(),
        ]);
    }

    /* ================================
     * CREATE
     * ================================ */
    public function create()
    {
        return Inertia::render('Admin/InventoryLocations/Create', [
            'types' => InventoryLocation::options(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|string|in:' . implode(',', InventoryLocation::TYPES),
        ]);

        $location = InventoryLocation::create($data);
        $this->inventory->createMissingStockRowsForLocation($location);

        return redirect()
            ->route('admin.inventory-locations.index')
            ->with('success', 'Inventory location created.');
    }

    /* ================================
     * EDIT
     * ================================ */
    public function edit(InventoryLocation $inventoryLocation)
    {
        return Inertia::render('Admin/InventoryLocations/Edit', [
            'location' => $inventoryLocation,
            'types' => InventoryLocation::options(),
        ]);
    }

    public function update(Request $request, InventoryLocation $inventoryLocation)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|string|in:' . implode(',', InventoryLocation::TYPES),
        ]);

        $inventoryLocation->update($data);

        return redirect()
            ->route('admin.inventory-locations.index')
            ->with('success', 'Inventory location updated.');
    }

    /* ================================
     * DELETE
     * ================================ */
    public function destroy(InventoryLocation $inventoryLocation)
    {
        abort_if(
            $inventoryLocation->stocks()->where('quantity', '>', 0)->exists(),
            422,
            'Location still has stock.'
        );
        abort_if(
            $inventoryLocation->movements()->exists(),
            422,
            'Location already has stock history and cannot be deleted.'
        );

        $inventoryLocation->delete();

        return back()->with('success', 'Inventory location deleted.');
    }
}
