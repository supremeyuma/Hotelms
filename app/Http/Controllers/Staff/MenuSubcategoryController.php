<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuSubcategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\MenuPrepTimeService;


class MenuSubcategoryController extends Controller
{
    public function store(Request $request)
    {
        MenuSubcategory::create($request->validate([
            'menu_category_id' => 'required|exists:menu_categories,id',
            'name' => 'required|string',
            'sort_order' => 'integer'
        ]));

        return back();
    }


    public function update(Request $request, MenuSubcategory $subcategory)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'menu_category_id' => 'required|exists:menu_categories,id',
            'prep_time_adjustment' => 'nullable|integer',
        ]);

        $subcategory->update([
            'name' => $data['name'],
            'menu_category_id' => $data['menu_category_id'],
        ]);

        if (isset($data['prep_time_adjustment'])) {
            MenuPrepTimeService::adjustForSubcategory(
                $subcategory,
                (int) $data['prep_time_adjustment']
            );
        }

        return back();
    }



    public function destroy(MenuSubcategory $subcategory)
    {
        $subcategory->delete();
        return back();
    }

    public function edit(MenuSubcategory $subcategory)
    {
        return Inertia::render('Staff/Menu/EditSubcategory', [
            'subcategory' => $subcategory->load('category')
        ]);
    }

    public function toggle(MenuSubcategory $subcategory)
    {
        $subcategory->update(['is_active' => ! $subcategory->is_active]);
        return back();
    }
    public function reorder(Request $request)
    {
        // Optionally validate that the category_id exists
        $request->validate([
            'subcategories' => 'required|array',
            'subcategories.*.id' => 'required|exists:menu_sub_categories,id',
        ]);

        foreach ($request->subcategories as $row) {
            MenuSubCategory::where('id', $row['id'])
                ->update(['sort_order' => $row['sort_order']]);
        }

        return back();
    }

}
