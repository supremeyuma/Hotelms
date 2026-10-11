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

        $roles = [
            [
                'name' => 'Super Admin',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Hotel Manager',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Front Desk Staff',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Housekeeping Staff',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Inventory Manager',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Accountant',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Maintenance Staff',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Restaurant Staff',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Guest',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now
            ],
        ];

        // slug is NOT NULL in the roles migration. Every other seeder sets it
        // explicitly; this one inserts plain DB rows and used to omit it, which
        // makes `migrate:fresh --seed` fail the moment RolesSeeder runs.
        foreach ($roles as $role) {
            $role['slug'] = Str::slug($role['name']);
            DB::table('roles')->insert($role);
        }
    }
}
