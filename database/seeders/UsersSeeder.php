<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates admins, managers, staff and guest users (10+ staff, 10+ guests)
 */
class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // roles map
        $roles = DB::table('roles')->pluck('id', 'slug')->toArray();

        // Admin / MD
        $mdId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'role_id' => $roles['md'] ?? null,
            'name' => 'Chinonso Okafor',
            'email' => 'ceo@email.com',
            'password' => Hash::make('1234'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Manager
        $managerId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'role_id' => $roles['manager'] ?? null,
            'name' => 'Aisha Bello',
            'email' => 'manager@email.com',
            'password' => Hash::make('ManagerPass1!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Inventory manager
        $invManagerId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'role_id' => $roles['inventory_manager'] ?? null,
            'name' => 'Daniel Mensah',
            'email' => 'inventory@email.com',
            'password' => Hash::make('Inventory123!'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Staff (10)
        $staffNames = [
            ['name'=>'Grace Adebayo','email'=>'grace.ad@email.com','position'=>'Housekeeping'],
            ['name'=>'Emeka Nwosu','email'=>'emeka.nw@email.com','position'=>'Kitchen'],
            ['name'=>'Bolaji Ojo','email'=>'bolaji.oj@email.com','position'=>'Receptionist'],
            ['name'=>'Sadiq Ibrahim','email'=>'sadiq.ib@email.com','position'=>'Maintenance'],
            ['name'=>'Ruth Eze','email'=>'ruth.ez@email.com','position'=>'Housekeeping'],
            ['name'=>'Tunde Oladipo','email'=>'tunde.ol@email.com','position'=>'Kitchen'],
            ['name'=>'Mercy Okoye','email'=>'mercy.ok@email.com','position'=>'Laundry'],
            ['name'=>'John Doe','email'=>'john.doe@email.com','position'=>'Waiter'],
            ['name'=>'Femi Johnson','email'=>'femi.jo@email.com','position'=>'Bellboy'],
            ['name'=>'Amina Yusuf','email'=>'amina.yu@email.com','position'=>'Housekeeping'],
        ];

        foreach ($staffNames as $s) {
            DB::table('users')->insert([
                'uuid' => (string) Str::uuid(),
                'role_id' => $roles['staff'] ?? null,
                'name' => $s['name'],
                'email' => $s['email'],
                'password' => Hash::make('StaffPass1!'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Guests (10)
        $guestNames = [
            ['name'=>'Adebimpe Oladunni','email'=>'bimpe.guest@example.com'],
            ['name'=>'Michael Smith','email'=>'michael.smith@example.com'],
            ['name'=>'Ngozi Chukwu','email'=>'ngozi.c@example.com'],
            ['name'=>'Samantha Brown','email'=>'samantha.b@example.com'],
            ['name'=>'Olufemi Adeyemi','email'=>'femi.a@example.com'],
            ['name'=>'Chen Wei','email'=>'chen.wei@example.com'],
            ['name'=>'Maria Rodriguez','email'=>'maria.rodriguez@example.com'],
            ['name'=>'Kwame Nkrumah','email'=>'kwame.n@example.com'],
            ['name'=>'Lilian Okon','email'=>'lilian.ok@example.com'],
            ['name'=>'Peter Obi','email'=>'peter.ob@example.com'],
        ];

        foreach ($guestNames as $g) {
            DB::table('users')->insert([
                'uuid' => (string) Str::uuid(),
                'role_id' => $roles['guest'] ?? null,
                'name' => $g['name'],
                'email' => $g['email'],
                'password' => Hash::make('GuestPass1!'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
