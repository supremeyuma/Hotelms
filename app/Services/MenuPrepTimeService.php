<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\MenuSubcategory;
use Illuminate\Support\Facades\DB;

class MenuPrepTimeService
{
    /**
     * Adjust prep time for a single item
     */
    public static function adjustForItem(MenuItem $item, int $minutes): void
    {
        DB::transaction(function () use ($item, $minutes) {
            $item->update([
                'prep_time_minutes' => DB::raw(
                    'GREATEST(0, COALESCE(prep_time_minutes,0) + ' . $minutes . ')'
                )
            ]);
        });
    }

    /**
     * Adjust prep time for a subcategory AND all its items
     */
    public static function adjustForSubcategory(MenuSubcategory $subcategory, int $minutes): void
    {
        DB::transaction(function () use ($subcategory, $minutes) {

            // Subcategory itself
            $subcategory->update([
                'prep_time_minutes' => DB::raw(
                    'GREATEST(0, COALESCE(prep_time_minutes,0) + ' . $minutes . ')'
                )
            ]);

            // All items under this subcategory
            $subcategory->items()->update([
                'prep_time_minutes' => DB::raw(
                    'GREATEST(0, COALESCE(prep_time_minutes,0) + ' . $minutes . ')'
                )
            ]);
        });
    }

    /**
     * Adjust prep time for a category, its subcategories and ALL items
     */
    public static function adjustForCategory(MenuCategory $category, int $minutes): void
    {
        DB::transaction(function () use ($category, $minutes) {

            // Category itself
            $category->update([
                'prep_time_minutes' => DB::raw(
                    'GREATEST(0, COALESCE(prep_time_minutes,0) + ' . $minutes . ')'
                )
            ]);

            // Subcategories
            $category->subcategories()->update([
                'prep_time_minutes' => DB::raw(
                    'GREATEST(0, COALESCE(prep_time_minutes,0) + ' . $minutes . ')'
                )
            ]);

            // Items directly under category
            $category->items()->whereNull('menu_subcategory_id')->update([
                'prep_time_minutes' => DB::raw(
                    'GREATEST(0, COALESCE(prep_time_minutes,0) + ' . $minutes . ')'
                )
            ]);

            // Items under subcategories
            MenuItem::whereIn(
                'menu_subcategory_id',
                $category->subcategories()->pluck('id')
            )->update([
                'prep_time_minutes' => DB::raw(
                    'GREATEST(0, COALESCE(prep_time_minutes,0) + ' . $minutes . ')'
                )
            ]);
        });
    }
}
