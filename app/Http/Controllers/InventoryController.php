<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Http\Requests\Inventory\StoreInventoryRequest;
use App\Http\Requests\Inventory\UpdateInventoryRequest;
use App\Services\InventoryService;
use App\Services\AuditLogger;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(InventoryItem::class, 'inventory_item');
    }

    public function store(StoreInventoryRequest $request, InventoryService $service)
    {
        $item = $service->createItem($request->validated());

        AuditLogger::log('inventory_created', 'InventoryItem', $item->id);

        return back()->with('success', 'Inventory item created.');
    }

    public function update(UpdateInventoryRequest $request, InventoryItem $inventory_item, InventoryService $service)
    {
        $service->updateItem($inventory_item, $request->validated());

        AuditLogger::log('inventory_updated', 'InventoryItem', $inventory_item->id);

        return back()->with('success', 'Inventory updated.');
    }
}
