<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles & permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = [
            'manage_dashboard',
            'view_inventory',
            'manage_inventory',
            'view_orders',
            'manage_orders',
            'manage_users',
            'view_reports',
            'manage_reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $roles = [
            'Guest' => ['view_inventory'],
            'Staff' => ['view_inventory', 'view_orders'],
            'Inventory Manager' => ['view_inventory', 'manage_inventory'],
            'Manager' => ['manage_dashboard', 'view_inventory', 'view_orders', 'view_reports'],
            'MD' => ['manage_dashboard', 'manage_users', 'manage_inventory', 'manage_reports'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
