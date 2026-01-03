<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use App\Services\MenuPrepTimeService;
use App\Models\MenuItemImage;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    public function index(Request $request)
    {
        $area = $request->query('area', 'kitchen');

        $categories = MenuCategory::with([
            'items.images',
            'subcategories.items.images',
        ])
        ->where(fn ($q) =>
            $q->where('type', $area)->orWhere('type', 'both')
        )
        ->orderBy('sort_order')
        ->get();

        return Inertia::render('Staff/Menu/Index', [
            'categories' => $categories,
            'area' => $area,
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_category_id' => 'required|exists:menu_categories,id',
            'menu_subcategory_id' => [
                'nullable',
                Rule::exists('menu_subcategories', 'id')
                    ->where('menu_category_id', $request->menu_category_id),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'service_area' => 'required|in:kitchen,bar',
            'is_available' => 'required|boolean',
            'images.*' => 'image|max:8192',
        ]);

        $item = MenuItem::create($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('menu-items', 'public');

                $item->images()->create([
                    'path' => $path
                ]);
            }
        }

        return back();
    }


    public function update(Request $request, MenuItem $item)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'menu_category_id' => 'required|exists:menu_categories,id',
            'menu_subcategory_id' => 'nullable|exists:menu_subcategories,id',
            'prep_time_adjustment' => 'nullable|integer',
            'is_available' => 'required|boolean',
        ]);

        $item->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'menu_category_id' => $data['menu_category_id'],
            'menu_subcategory_id' => $data['menu_subcategory_id'] ?? null,
            'is_available' => $data['is_available'],
        ]);

        if (array_key_exists('prep_time_adjustment', $data)) {
            MenuPrepTimeService::adjustForItem($item, (int) $data['prep_time_adjustment']);
        }

        return back();
    }





    public function destroy(MenuItem $item)
    {
        $item->delete();
        return back();
    }

    public function edit(MenuItem $item)
    {
        return Inertia::render('Staff/Menu/EditItem', [
            'item' => $item->load('images'),
            'categories' => MenuCategory::with('subcategories')->get()
        ]);
    }

    public function toggle(MenuItem $item)
    {
        $item->update(['is_available' => ! $item->is_available]);
        return back();
    }

    public function reorder(Request $request)
    {
        foreach ($request->items as $row) {
            MenuItem::where('id', $row['id'])
                ->update(['sort_order' => $row['sort_order']]);
        }

        return back();
    }

    public function deleteImage(MenuItemImage $image)
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back();
    }



}
