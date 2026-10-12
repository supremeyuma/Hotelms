<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class MissingOperationalRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'accountant',
            'hr',
            'staff',
        ] as $roleName) {
            // `roles.slug` is NOT NULL; Spatie's findOrCreate() ignores it.
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['slug' => $roleName]
            );

            if (blank($role->slug)) {
                $role->slug = $roleName;
                $role->save();
            }
        }
    }
}
