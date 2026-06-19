<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\DrinkStock;
use App\Models\Department;
use App\Models\User;
use App\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ClubDrinkSeeder extends Seeder
{
    public function run(): void
    {
        $category = MenuCategory::firstOrCreate(
            ['name' => 'Club Lounge', 'type' => 'bar'],
            ['is_active' => true, 'sort_order' => 1]
        );

        $drinks = [
            ['name' => 'Tusker Lager', 'description' => '330ml bottle', 'price' => 450],
            ['name' => 'White Cap', 'description' => '330ml bottle', 'price' => 450],
            ['name' => 'Safari Lager', 'description' => '330ml bottle', 'price' => 450],
            ['name' => 'Guinness', 'description' => '330ml bottle', 'price' => 550],
            ['name' => 'Smirnoff Ice', 'description' => '330ml bottle', 'price' => 500],
            ['name' => 'Captain Morgan Gold', 'description' => '50ml measure', 'price' => 600],
            ['name' => 'Johnnie Walker Black', 'description' => '50ml measure', 'price' => 1200],
            ['name' => 'Jameson', 'description' => '50ml measure', 'price' => 800],
            ['name' => 'Baileys', 'description' => '50ml measure', 'price' => 700],
            ['name' => 'Martell VS', 'description' => '50ml measure', 'price' => 1500],
            ['name' => 'Moët & Chandon', 'description' => '750ml bottle', 'price' => 8500],
            ['name' => 'Veuve Clicquot', 'description' => '750ml bottle', 'price' => 9500],
            ['name' => 'Coke', 'description' => '330ml can', 'price' => 200],
            ['name' => 'Fanta Orange', 'description' => '330ml can', 'price' => 200],
            ['name' => 'Sprite', 'description' => '330ml can', 'price' => 200],
            ['name' => 'Keringet Water', 'description' => '500ml bottle', 'price' => 150],
            ['name' => 'Fresh Orange Juice', 'description' => '300ml', 'price' => 400],
            ['name' => 'Fresh Pineapple Juice', 'description' => '300ml', 'price' => 400],
            ['name' => 'Fresh Passion Juice', 'description' => '300ml', 'price' => 450],
            ['name' => 'Kenyan Coffee', 'description' => 'Freshly brewed', 'price' => 350],
            ['name' => 'Mocha', 'description' => 'With chocolate', 'price' => 450],
            ['name' => 'Latte', 'description' => 'Espresso with milk', 'price' => 400],
            ['name' => 'Cappuccino', 'description' => 'Italian classic', 'price' => 400],
            ['name' => 'English Breakfast Tea', 'description' => 'Pot service', 'price' => 300],
            ['name' => 'Masala Chai', 'description' => 'Spiced tea', 'price' => 350],
        ];

        foreach ($drinks as $drink) {
            $item = MenuItem::updateOrCreate(
                ['name' => $drink['name'], 'service_area' => 'club'],
                [
                    'menu_category_id' => $category->id,
                    'description' => $drink['description'],
                    'price' => $drink['price'],
                    'is_available' => true,
                    'service_area' => 'club',
                ]
            );

            DrinkStock::updateOrCreate(
                ['menu_item_id' => $item->id],
                [
                    'full_bottles' => 10,
                    'opened_bottles' => 0,
                    'pours_per_bottle' => 25,
                    'low_stock_threshold' => 3,
                    'is_active' => true,
                ]
            );
        }

        $department = Department::firstOrCreate(['name' => 'Club Lounge']);

        $staffDefs = [
            ['name' => 'Club Bartender', 'email' => 'club.bartender@email.com', 'role' => 'club_staff', 'position' => 'Bartender'],
            ['name' => 'Club Supervisor', 'email' => 'club.supervisor@email.com', 'role' => 'club_supervisor', 'position' => 'Club Supervisor'],
        ];

        foreach ($staffDefs as $def) {
            $user = User::withTrashed()->firstOrNew(['email' => $def['email']]);
            $user->uuid = $user->uuid ?: (string) Str::uuid();
            $user->name = $def['name'];
            $user->password = '11111111';
            $user->email_verified_at = now();
            $user->department_id = $department->id;
            $user->suspended_at = null;
            $user->save();
            if ($user->trashed()) {
                $user->restore();
            }
            $user->syncRoles([$def['role']]);

            $profile = StaffProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'position' => $def['position'],
                    'phone' => '+254700' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                    'meta' => ['employment_status' => 'active'],
                ]
            );

            $profile->storeActionCode('111111');
            $profile->save();
        }
    }
}
