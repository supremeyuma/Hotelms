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
        $recipes = MenuInventoryRecipe::query()
            ->with([
                'menuItem.category:id,name',
                'menuItem.subcategory:id,name',
                'inventoryItem' => fn ($query) => $query->withSum('stocks as total_stock', 'quantity'),
            ])
            ->get();

        $menuItemRecipeCounts = $recipes
            ->groupBy('menu_item_id')
            ->map(fn ($group) => $group->count());

        return Inertia::render('Admin/MenuRecipes/Index', [
            'recipes' => $recipes->map(function ($recipe) {
                $totalStock = (float) ($recipe->inventoryItem->total_stock ?? 0);

                return [
                    'id' => $recipe->id,
                    'quantity' => (float) $recipe->quantity,
                    'menu_item' => [
                        'id' => $recipe->menuItem->id,
                        'name' => $recipe->menuItem->name,
                        'service_area' => $recipe->menuItem->service_area,
                        'is_available' => (bool) $recipe->menuItem->is_available,
                        'prep_time_minutes' => $recipe->menuItem->effective_prep_time,
                        'category_name' => $recipe->menuItem->category?->name,
                        'subcategory_name' => $recipe->menuItem->subcategory?->name,
                    ],
                    'inventory_item' => [
                        'id' => $recipe->inventoryItem->id,
                        'name' => $recipe->inventoryItem->name,
                        'sku' => $recipe->inventoryItem->sku,
                        'unit' => $recipe->inventoryItem->unit,
                        'total_stock' => $totalStock,
                        'low_stock' => $totalStock <= (float) ($recipe->inventoryItem->low_stock_threshold ?? 0),
                    ],
                ];
            }),
            'menuItems' => MenuItem::query()
                ->with(['category:id,name', 'subcategory:id,name'])
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'menu_category_id',
                    'menu_subcategory_id',
                    'service_area',
                    'is_available',
                    'prep_time_minutes',
                ])
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'service_area' => $item->service_area,
                    'is_available' => (bool) $item->is_available,
                    'prep_time_minutes' => $item->effective_prep_time,
                    'category_name' => $item->category?->name,
                    'subcategory_name' => $item->subcategory?->name,
                    'recipe_count' => $menuItemRecipeCounts->get($item->id, 0),
                ]),
            'inventoryItems' => InventoryItem::query()
                ->withSum('stocks as total_stock', 'quantity')
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit', 'low_stock_threshold'])
                ->map(function ($item) {
                    $totalStock = (float) ($item->total_stock ?? 0);

                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'sku' => $item->sku,
                        'unit' => $item->unit,
                        'total_stock' => $totalStock,
                        'low_stock' => $totalStock <= (float) ($item->low_stock_threshold ?? 0),
                    ];
                }),
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
