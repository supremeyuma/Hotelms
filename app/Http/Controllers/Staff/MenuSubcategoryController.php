<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuSubcategory;
use Illuminate\Http\Request;

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
        $subcategory->update($request->all());
        return back();
    }

    public function destroy(MenuSubcategory $subcategory)
    {
        $subcategory->delete();
        return back();
    }
}
