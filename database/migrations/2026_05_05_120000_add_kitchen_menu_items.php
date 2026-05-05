<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORY_ITEMS = [
        'Proteins' => [
            ['name' => 'Chicken', 'price' => 4000],
            ['name' => 'Goat meat', 'price' => 4000],
            ['name' => 'Beef', 'price' => 3000],
            ['name' => 'Cow leg', 'price' => 4000],
            ['name' => 'Assorted', 'price' => 4000],
            ['name' => 'Fish', 'price' => 8000],
        ],
        'Swallow' => [
            ['name' => 'Garri', 'price' => 2000],
            ['name' => 'Semo', 'price' => 2000],
            ['name' => 'Poundo', 'price' => 2000],
        ],
        'Pepper Soup/Peppered Meat' => [
            ['name' => 'Chicken', 'price' => 5000],
            ['name' => 'Goat meat', 'price' => 5000],
            ['name' => 'Beef', 'price' => 4000],
            ['name' => 'Fish', 'price' => 8000],
            ['name' => 'Cow leg', 'price' => 4500],
            ['name' => 'Assorted', 'price' => 4500],
        ],
        'Egg Meals' => [
            ['name' => 'Fried Egg', 'price' => 2000],
            ['name' => 'Boiled egg', 'price' => 1500],
            ['name' => 'Egg Sauce', 'price' => 2000],
        ],
        'Rice Meal' => [
            ['name' => 'White Rice', 'price' => 2000],
            ['name' => 'Jollof Rice', 'price' => 3000],
            ['name' => 'Fried Rice', 'price' => 3000],
            ['name' => 'Coconut Rice', 'price' => 4000],
            ['name' => 'Native Jollof', 'price' => 3500],
        ],
        'Pasta' => [
            ['name' => 'Spaghetti', 'price' => 3500],
            ['name' => 'Noodles', 'price' => 3000],
        ],
        'Soup Meals' => [
            ['name' => 'Afang soup', 'price' => 3500],
            ['name' => 'Okro Soup', 'price' => 3000],
            ['name' => 'Vegetable', 'price' => 3000],
            ['name' => 'Melon soup', 'price' => 3000],
            ['name' => 'White Soup', 'price' => 4000],
            ['name' => 'Fisherman', 'price' => 12000],
        ],
        'Breakfast' => [
            ['name' => 'Bread', 'price' => 1500],
            ['name' => 'Tea/Coffee', 'price' => 1500],
        ],
        'Comfort Meals' => [
            ['name' => 'Stew', 'price' => 1500],
            ['name' => 'Curry Sauce', 'price' => 1500],
            ['name' => 'Casserole', 'price' => 2000],
        ],
        'Small Plates' => [
            ['name' => 'Yam', 'price' => 3000],
            ['name' => 'Plantain', 'price' => 3000],
            ['name' => 'Potato', 'price' => 3000],
            ['name' => 'Coleslaw', 'price' => 2000],
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (array_keys(self::CATEGORY_ITEMS) as $index => $categoryName) {
            $existingCategory = DB::table('menu_categories')
                ->where('name', $categoryName)
                ->where('type', 'kitchen')
                ->first();

            if ($existingCategory) {
                DB::table('menu_categories')
                    ->where('id', $existingCategory->id)
                    ->update([
                        'is_active' => true,
                        'sort_order' => $index + 1,
                        'updated_at' => $now,
                    ]);

                $categoryId = $existingCategory->id;
            } else {
                $categoryId = DB::table('menu_categories')->insertGetId([
                    'name' => $categoryName,
                    'type' => 'kitchen',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (self::CATEGORY_ITEMS[$categoryName] as $item) {
                $existingItem = DB::table('menu_items')
                    ->where('menu_category_id', $categoryId)
                    ->where('service_area', 'kitchen')
                    ->where('name', $item['name'])
                    ->first();

                $itemPayload = [
                    'menu_category_id' => $categoryId,
                    'service_area' => 'kitchen',
                    'name' => $item['name'],
                    'menu_subcategory_id' => null,
                    'description' => null,
                    'price' => $item['price'],
                    'prep_time_minutes' => null,
                    'is_available' => true,
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if ($existingItem) {
                    DB::table('menu_items')
                        ->where('id', $existingItem->id)
                        ->update($itemPayload);
                } else {
                    DB::table('menu_items')->insert($itemPayload + [
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::CATEGORY_ITEMS as $categoryName => $items) {
            $categoryId = DB::table('menu_categories')
                ->where('name', $categoryName)
                ->where('type', 'kitchen')
                ->value('id');

            if (! $categoryId) {
                continue;
            }

            DB::table('menu_items')
                ->where('menu_category_id', $categoryId)
                ->where('service_area', 'kitchen')
                ->whereIn('name', array_column($items, 'name'))
                ->delete();

            $hasItems = DB::table('menu_items')
                ->where('menu_category_id', $categoryId)
                ->exists();

            $hasSubcategories = DB::table('menu_subcategories')
                ->where('menu_category_id', $categoryId)
                ->exists();

            if (! $hasItems && ! $hasSubcategories) {
                DB::table('menu_categories')
                    ->where('id', $categoryId)
                    ->delete();
            }
        }
    }
};
