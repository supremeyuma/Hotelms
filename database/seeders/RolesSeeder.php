<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        /*$roles = [
            ['name' => 'MD', 'slug' => 'md', 'permissions' => json_encode(['*']), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Manager', 'slug' => 'manager', 'permissions' => json_encode(['manage_bookings','manage_staff','view_reports']), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Staff', 'slug' => 'staff', 'permissions' => json_encode(['process_orders','update_maintenance']), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Guest', 'slug' => 'guest', 'permissions' => json_encode([]), 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Inventory Manager', 'slug' => 'inventory_manager', 'permissions' => json_encode(['manage_inventory']), 'created_at' => $now, 'updated_at' => $now],
        ];*/

        //DB::table('roles')->insert($roles);
    }
}
