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
            Role::findOrCreate($roleName, 'web');
        }
    }
}
