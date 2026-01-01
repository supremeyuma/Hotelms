<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;

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
        $category->update($request->all());
        return back();
    }

    public function destroy(MenuCategory $category)
    {
        $category->delete();
        return back();
    }
}
