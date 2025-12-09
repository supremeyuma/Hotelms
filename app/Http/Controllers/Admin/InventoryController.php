<?php
// ========================================================
// Admin\InventoryController.php
// Namespace: App\Http\Controllers\Admin
// ========================================================
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Services\InventoryService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    protected InventoryService $service;

    public function __construct(InventoryService $service)
    {
        $this->middleware(['auth','role:manager|md']);
        $this->service = $service;
    }

    public function index()
    {
        $items = InventoryItem::paginate(25);
        return Inertia::render('Admin/Inventory/Index', compact('items'));
    }

    public function create()
    {
        return Inertia::render('Admin/Inventory/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'=>'required|string|max:191',
            'sku'=>'required|string|unique:inventory_items,sku',
            'quantity'=>'required|integer|min:0',
            'unit'=>'nullable|string',
            'meta'=>'nullable|array',
        ]);

        $item = $this->service->createItem($data);

        AuditLogger::log('inventory_created', 'InventoryItem', $item->id, ['data' => $data]);

        return redirect()->route('inventory.index')->with('success','Inventory item created.');
    }

    public function edit(InventoryItem $inventory)
    {
        return Inertia::render('Admin/Inventory/Edit', ['item'=>$inventory]);
    }

    public function update(Request $request, InventoryItem $inventory)
    {
        $data = $request->validate([
            'name'=>'required|string|max:191',
            'sku'=>'required|string|unique:inventory_items,sku,'.$inventory->id,
            'quantity'=>'required|integer|min:0',
            'unit'=>'nullable|string',
            'meta'=>'nullable|array',
        ]);

        $this->service->updateItem($inventory, $data);

        AuditLogger::log('inventory_updated', 'InventoryItem', $inventory->id, ['data'=>$data]);

        return redirect()->route('inventory.index')->with('success','Inventory updated.');
    }

    /**
     * usage - deduct stock and record log
     */
    public function useItem(Request $request, InventoryItem $inventory)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'nullable|string'
        ]);

        $before = $inventory->quantity;
        $inventory->decrement('quantity', $data['quantity']);

        $log = InventoryLog::create([
            'inventory_item_id' => $inventory->id,
            'staff_id' => $request->user()->id,
            'change' => -abs($data['quantity']),
            'meta' => ['reason' => $data['reason'] ?? null, 'before' => $before, 'after' => $inventory->quantity]
        ]);

        AuditLogger::log('inventory_used', 'InventoryItem', $inventory->id, ['change' => $data['quantity']]);

        return back()->with('success','Inventory updated.');
    }
}
