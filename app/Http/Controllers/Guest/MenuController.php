<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Guest/Menu', [
            'categories' => MenuCategory::with([
                'subcategories.items' => fn ($q) => $q->where('is_available', true),
                'items' => fn ($q) => $q
                    ->whereNull('menu_subcategory_id')
                    ->where('is_available', true)
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
        ]);
    }
}
