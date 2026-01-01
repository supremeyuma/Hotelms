<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $area = $request->query('area', 'kitchen');

        return Inertia::render('Staff/Menu/Manage', [
            'area' => $area,
            'categories' => MenuCategory::with([
                'subcategories.items',
                'items' => fn ($q) => $q->whereNull('menu_subcategory_id')
            ])
            ->where(fn ($q) =>
                $q->where('type', $area)->orWhere('type', 'both')
            )
            ->orderBy('sort_order')
            ->get()
        ]);
    }

    public function store(Request $request)
    {
        MenuItem::create($request->validate([
            'menu_category_id' => 'required|exists:menu_categories,id',
            'menu_subcategory_id' => 'nullable|exists:menu_subcategories,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'service_area' => 'required|in:kitchen,bar'
        ]));

        return back();
    }

    public function update(Request $request, MenuItem $item)
    {
        $item->update($request->all());
        return back();
    }

    public function destroy(MenuItem $item)
    {
        $item->delete();
        return back();
    }
}
