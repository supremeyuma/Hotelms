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
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    protected InventoryService $service;
    protected AuditLoggerService $auditLogger;

    public function __construct(InventoryService $service, AuditLoggerService $auditLogger)
    {
        $this->middleware(['auth','role:manager|md']);
        $this->service = $service;
        $this->auditLogger = $auditLogger;
    }

    public function index()
    {
        $items = InventoryItem::paginate(25)->through(fn ($item) => [
        // Spreads the existing model attributes
        ...$item->toArray(),
        // Adds the custom calculated field
        'low_stock' => $item->isLowStock(), 
    ]);
    //dd($items->toArray());
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
            'meta'=>'nullable|array',]);
        $item = $this->service->createItem($data);

        $this->auditLogger->log('inventory_created', 'InventoryItem', $item->id, ['data' => $data]);

        return redirect()->route('admin.inventory.index')->with('success','Inventory item created.');
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
            'meta'=>'nullable|array',]);
        $this->service->updateItem($inventory, $data);

        $this->auditLogger->log('inventory_updated', 'InventoryItem', $inventory->id, ['data'=>$data]);

        return redirect()->route('admin.inventory.index')->with('success','Inventory updated.');

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
            'type' => 'usage',
            'meta' => ['reason' => $data['reason'] ?? null, 
                        'department_id' => $data['department_id'] ?? null,
                        'before' => $before, 
                        'after' => $inventory->quantity]]);
        $this->auditLogger->log('inventory_used', 'InventoryItem', $inventory->id, ['change' => $data['quantity']]);

        return back()->with('success','Inventory updated.');
    }

    public function show(InventoryItem $inventory)
    {
        $inventory->load([
            'logs.staff',
            'logs' => fn ($q) => $q->latest()
        ]);

        return Inertia::render('Admin/Inventory/Show', [
            'item' => $inventory
        ]);
    }

    public function undoLog(InventoryLog $log)
    {
        abort_if($log->change >= 0, 403);

        $item = $log->inventoryItem;
        //dd($item);

        DB::transaction(function () use ($item, $log) {
            $item->increment('quantity', abs($log->change));

            InventoryLog::create([
                'inventory_item_id' => $item->id,
                'staff_id' => auth()->id(),
                'change' => abs($log->change),
                'type' => 'undo_usage',
                'meta' => [
                    'undo_log_id' => $log->id
                ]
            ]);

            $log->update(['meta->undone' => true]);
        });

        return back()->with('success', 'Inventory usage undone.');
    }

    public function reconcile(Request $request, InventoryItem $inventory)
    {
        $data = $request->validate([
            'actual_quantity' => 'required|integer|min:0',
            'note' => 'required|string'
        ]);

        $difference = $data['actual_quantity'] - $inventory->quantity;

        DB::transaction(function () use ($inventory, $difference, $data) {
            $inventory->update(['quantity' => $data['actual_quantity']]);

            InventoryLog::create([
                'inventory_item_id' => $inventory->id,
                'staff_id' => auth()->id(),
                'change' => $difference,
                'meta' => [
                    'type' => 'reconciliation',
                    'note' => $data['note']
                ]
            ]);
        });

        return back()->with('success', 'Inventory reconciled.');
    }



}
