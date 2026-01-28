<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuInventoryRecipe;
use App\Models\MenuItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuInventoryRecipeController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/MenuRecipes/Index', [
            'recipes' => MenuInventoryRecipe::with([
                'menuItem',
                'inventoryItem'
            ])->get()->map(fn ($r) => [
                'id' => $r->id,
                'quantity' => $r->quantity,
                'menu_item' => $r->menuItem,
                'inventory_item' => [
                    'id' => $r->inventoryItem->id,
                    'name' => $r->inventoryItem->name,
                    'unit' => $r->inventoryItem->unit,
                    'total_stock' => $r->inventoryItem->totalStock(),
                ],
            ]),
            'menuItems' => MenuItem::all(['id', 'name']),
            'inventoryItems' => InventoryItem::all(['id', 'name', 'unit']),
        ]);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'quantity' => 'required|numeric|min:0.01',
        ]);

        MenuInventoryRecipe::updateOrCreate(
            [
                'menu_item_id' => $data['menu_item_id'],
                'inventory_item_id' => $data['inventory_item_id'],
            ],
            ['quantity' => $data['quantity']]
        );

        return back()->with('success', 'Recipe saved.');
    }

    public function destroy(MenuInventoryRecipe $recipe)
    {
        $recipe->delete();

        return back()->with('success', 'Recipe removed.');
    }
}
