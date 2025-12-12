<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Adds staff_profiles for staff users (position, phone, action_code_hash)
 */
class StaffProfilesSeeder extends Seeder
{
    public function run(): void
    {
        $staffUsers = DB::table('users')->whereIn('role_id', function ($q) {
            $q->select('id')->from('roles')->where('slug', 'staff');
        })->get();

        foreach ($staffUsers as $index => $u) {
            DB::table('staff_profiles')->insert([
                'user_id' => $u->id,
                'position' => 'Staff',
                'phone' => sprintf('+23480%07d', 1000000 + $index),
                'meta' => json_encode(['shift' => ($index % 3 === 0) ? 'morning' : 'evening']),
                'action_code_hash' => Hash::make('1234'), // default action code for demo
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Manager, MD and inventory manager profiles
        $manager = DB::table('users')->where('email', 'manager@lagosharborhotel.com')->first();
        if ($manager) {
            DB::table('staff_profiles')->insert([
                'user_id' => $manager->id,
                'position' => 'General Manager',
                'phone' => '+2348090000001',
                'meta' => json_encode(['office' => 'Main']),
                'action_code_hash' => Hash::make('4321'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $md = DB::table('users')->where('email', 'ceo@lagosharborhotel.com')->first();
        if ($md) {
            DB::table('staff_profiles')->insert([
                'user_id' => $md->id,
                'position' => 'Managing Director',
                'phone' => '+2348090000000',
                'meta' => json_encode(['office' => 'CEO Suite']),
                'action_code_hash' => Hash::make('9999'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $inv = DB::table('users')->where('email', 'inventory@lagosharborhotel.com')->first();
        if ($inv) {
            DB::table('staff_profiles')->insert([
                'user_id' => $inv->id,
                'position' => 'Inventory Manager',
                'phone' => '+2348090000002',
                'meta' => json_encode(['office' => 'Stockroom']),
                'action_code_hash' => Hash::make('7777'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
