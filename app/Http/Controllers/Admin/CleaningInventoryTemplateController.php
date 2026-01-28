<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CleaningInventoryTemplate;
use App\Models\RoomType;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use DB;

class CleaningInventoryTemplateController extends Controller
{
    public function index()
    {
        $templates = CleaningInventoryTemplate::with([
            'roomType',
            'inventoryItem.stocks'
        ])->get();

        return Inertia::render('Admin/CleaningTemplates/Index', [
            'templates' => $templates->map(fn ($t) => [
                'id' => $t->id,
                'quantity' => $t->quantity,
                'room_type' => $t->roomType,
                'inventory_item' => [
                    'id' => $t->inventoryItem->id,
                    'name' => $t->inventoryItem->name,
                    'unit' => $t->inventoryItem->unit,
                    'total_stock' => $t->inventoryItem->totalStock(),
                ]
            ]),
            'roomTypes' => RoomType::all(['id', 'title']),
            'inventoryItems' => InventoryItem::all(['id', 'name', 'unit']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        CleaningInventoryTemplate::updateOrCreate(
            [
                'room_type_id' => $data['room_type_id'],
                'inventory_item_id' => $data['inventory_item_id'],
            ],
            ['quantity' => $data['quantity']]
        );

        return back()->with('success', 'Template saved.');
    }

    /** INLINE UPDATE */
    public function update(Request $request, CleaningInventoryTemplate $template)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.01'
        ]);

        $template->update([
            'quantity' => $request->quantity
        ]);

        return back()->with('success', 'Quantity updated.');
    }

    /** CLONE TEMPLATES */
    public function clone(Request $request)
    {
        $data = $request->validate([
            'from_room_type_id' => 'required|exists:room_types,id',
            'to_room_type_id' => 'required|exists:room_types,id|different:from_room_type_id',
        ]);

        DB::transaction(function () use ($data) {
            $templates = CleaningInventoryTemplate::where(
                'room_type_id',
                $data['from_room_type_id']
            )->get();

            foreach ($templates as $t) {
                CleaningInventoryTemplate::updateOrCreate(
                    [
                        'room_type_id' => $data['to_room_type_id'],
                        'inventory_item_id' => $t->inventory_item_id,
                    ],
                    ['quantity' => $t->quantity]
                );
            }
        });

        return back()->with('success', 'Templates cloned.');
    }

    public function destroy(CleaningInventoryTemplate $template)
    {
        $template->delete();
        return back()->with('success', 'Template removed.');
    }
}
