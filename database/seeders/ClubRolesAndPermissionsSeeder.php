<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;

class ClubRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'club.pos.access',
            'club.pos.docket.open',
            'club.pos.docket.close',
            'club.pos.docket.void',
            'club.pos.docket.view',
            'club.pos.shift.open',
            'club.pos.shift.close',
            'club.pos.shift.reconcile',
            'club.pos.stock.view',
            'club.pos.stock.adjust',
            'club.pos.stock.stocktake',
            'club.pos.reports.view',
            'club.pos.reports.trends',
            'club.pos.settings.manage',
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $record = Permission::findOrCreate($perm, 'web');
            $permissionIds[$perm] = $record->id;
        }

        $now = now();

        $roles = [
            ['name' => 'club_staff', 'slug' => 'club-staff', 'guard_name' => 'web'],
            ['name' => 'club_supervisor', 'slug' => 'club-supervisor', 'guard_name' => 'web'],
        ];

        $roleIds = [];
        foreach ($roles as $roleData) {
            $existing = DB::table('roles')->where('name', $roleData['name'])->first();
            if ($existing) {
                DB::table('roles')->where('id', $existing->id)->update($roleData);
                $roleIds[$roleData['name']] = $existing->id;
            } else {
                $roleIds[$roleData['name']] = DB::table('roles')->insertGetId(
                    array_merge($roleData, ['created_at' => $now, 'updated_at' => $now])
                );
            }
        }

        $now = now();

        $staffPerms = [
            'club.pos.access',
            'club.pos.docket.open',
            'club.pos.docket.close',
            'club.pos.docket.view',
            'club.pos.shift.open',
            'club.pos.stock.view',
        ];

        $supervisorPerms = [
            'club.pos.access',
            'club.pos.docket.open',
            'club.pos.docket.close',
            'club.pos.docket.void',
            'club.pos.docket.view',
            'club.pos.shift.open',
            'club.pos.shift.close',
            'club.pos.shift.reconcile',
            'club.pos.stock.view',
            'club.pos.stock.adjust',
            'club.pos.stock.stocktake',
            'club.pos.reports.view',
            'club.pos.reports.trends',
            'club.pos.settings.manage',
        ];

        foreach ($staffPerms as $perm) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionIds[$perm],
                'role_id' => $roleIds['club_staff'],
            ]);
        }

        foreach ($supervisorPerms as $perm) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permissionIds[$perm],
                'role_id' => $roleIds['club_supervisor'],
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
