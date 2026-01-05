<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuSubcategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $area = $request->get('area'); // kitchen | bar

        dd($area);

        if (auth()->user()->hasRole('kitchen') && $area !== 'kitchen') {
            abort(403);
        }

        if (auth()->user()->hasRole('bar') && $area !== 'bar') {
            abort(403);
        }

        return Inertia::render('Staff/Menu/Index', [
            'area' => $area,
            'categories' => MenuCategory::with([
                'subcategories.items',
                'items' => fn ($q) => $q->whereNull('menu_subcategory_id')
            ])
            ->where(function ($q) use ($area) {
                $q->where('type', $area)->orWhere('type', 'both');
            })
            ->orderBy('sort_order')
            ->get(),
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'menu_category_id' => 'required|exists:menu_categories,id',
            'menu_subcategory_id' => 'nullable|exists:menu_subcategories,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'prep_time_minutes' => 'nullable|integer|min:0',
            'service_area' => 'required|in:kitchen,bar'
        ]);

        

        MenuItem::create($data);

        return back()->with('success', 'Menu item created');
    }

    public function updateItem(Request $request, MenuItem $item)
    {
        $item->update($request->all());
        return back()->with('success', 'Menu item updated');
    }

    public function destroyItem(MenuItem $item)
    {
        $item->delete();
        return back()->with('success', 'Menu item deleted');
    }

    public function bulkAddTime(Request $request)
    {
        $data = $request->validate([
            'minutes' => ['required','integer','min:1'],
            'scope' => ['required','in:menu,category,subcategory'],
            'id' => ['nullable','integer'],
        ]);

        $query = MenuItem::query();

        if ($data['scope'] === 'category') {
            $query->where('menu_category_id', $data['id']);
        }

        if ($data['scope'] === 'subcategory') {
            $query->where('menu_subcategory_id', $data['id']);
        }

        $query->update([
            'prep_time_minutes' => DB::raw(
                "COALESCE(prep_time_minutes,0) + {$data['minutes']}"
            )
        ]);

        return back();
    }

}
