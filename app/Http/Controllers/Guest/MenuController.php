<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MenuController extends Controller
{
   public function index(Request $request, string $token, string $type)
    {
        return Inertia::render('Guest/Menu', [
            'accessToken' => $token,
            'type' => $type,
            'categories' => MenuCategory::query()
                ->where('is_active', true)
                ->where('type', $type)
                ->where(function ($q) use ($type) {
                    $q->whereHas('items', fn ($i) =>
                        $i->whereNull('menu_subcategory_id')
                        ->where('is_active', true)
                        ->where('service_area', $type)
                    )
                    ->orWhereHas('subcategories.items', fn ($i) =>
                        $i->where('is_active', true)
                        ->where('service_area', $type)
                    );
                })
                ->with([
                    'items' => fn ($q) => $q
                        ->whereNull('menu_subcategory_id')
                        ->where('is_active', true)
                        ->where('service_area', $type)
                        ->with('images'),

                    'subcategories' => fn ($q) => $q
                        ->where('is_active', true)
                        ->whereHas('items', fn ($i) =>
                            $i->where('is_active', true)
                            ->where('service_area', $type)
                        )
                        ->with([
                            'items' => fn ($i) => $i
                                ->where('is_active', true)
                                ->where('service_area', $type)
                                ->with('images')
                        ]),
                ])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

}
