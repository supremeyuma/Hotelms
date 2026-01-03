<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Services\MenuPrepTimeService;

class MenuCategoryController extends Controller
{
    public function store(Request $request)
    {
        MenuCategory::create($request->validate([
            'name' => 'required|string',
            'type' => 'required|in:kitchen,bar,both',
            'sort_order' => 'integer'
        ]));

        return back();
    }


    public function update(Request $request, MenuCategory $category)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'prep_time_adjustment' => 'nullable|integer',
        ]);

        $category->update([
            'name' => $data['name'],
        ]);

        if (isset($data['prep_time_adjustment'])) {
            MenuPrepTimeService::adjustForCategory(
                $category,
                (int) $data['prep_time_adjustment']
            );
        }

        return back();
    }



    public function destroy(MenuCategory $category)
    {
        $category->delete();
        return back();
    }

    public function edit(MenuCategory $category)
    {
        return Inertia::render('Staff/Menu/EditCategory', [
            'category' => $category->load('subcategories')
        ]);
    }

    public function toggle(MenuCategory $category)
    {
        $category->update(['is_active' => ! $category->is_active]);
        return back();
    }
    public function reorder(Request $request)
    {
        foreach ($request->categories as $row) {
            MenuCategory::where('id', $row['id'])
                ->update(['sort_order' => $row['sort_order']]);
        }

        return back();
    }
}
