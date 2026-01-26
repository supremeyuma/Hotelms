<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryLocationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:manager|md']);
    }

    /* ================================
     * INDEX
     * ================================ */
    public function index()
    {
        return Inertia::render('Admin/InventoryLocations/Index', [
            'locations' => InventoryLocation::latest()->paginate(20)
        ]);
    }

    /* ================================
     * CREATE
     * ================================ */
    public function create()
    {
        return Inertia::render('Admin/InventoryLocations/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|string|max:100',
        ]);

        InventoryLocation::create($data);

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
            'location' => $inventoryLocation
        ]);
    }

    public function update(Request $request, InventoryLocation $inventoryLocation)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'type' => 'required|string|max:100',
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
        abort_if($inventoryLocation->stocks()->exists(), 422, 'Location has stock.');

        $inventoryLocation->delete();

        return back()->with('success', 'Inventory location deleted.');
    }
}
