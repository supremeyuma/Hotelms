<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The operational role set.
 *
 * These names are not cosmetic: they are the exact strings the `role:` middleware
 * in routes/*.php checks (`role:manager|md|superuser`, `role:frontdesk|md`, …),
 * and the exact names `syncRoles()` is called with. Renaming one here without
 * renaming it in the routes silently locks that whole section of the app out.
 *
 * A previous version seeded display names ("Hotel Manager", "Front Desk Staff").
 * Those satisfied no route, and `UsersSeeder`'s `roles.slug` lookups returned
 * null, so a fresh install had users with no usable role at all.
 *
 * `slug` is NOT NULL on every supported driver, so it is always written.
 * The human-readable label lives in the `permissions` JSON column, matching
 * LocalHotelDemoSeeder, and is metadata only — never an authorization input.
 */
class RolesSeeder extends Seeder
{
    /** name => label. The name is the authorization key; the label is display. */
    private const ROLES = [
        'superuser' => 'Superuser',
        'md' => 'Managing Director',
        'manager' => 'Hotel Manager',
        'frontdesk' => 'Front Desk',
        'clean' => 'Housekeeping',
        'laundry' => 'Laundry',
        'kitchen' => 'Kitchen',
        'bar' => 'Bar',
        'hr' => 'Human Resources',
        'accountant' => 'Accountant',
        'inventory' => 'Inventory',
        'staff' => 'Staff',
        'guest' => 'Guest',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $name => $label) {
            // updateOrInsert, not insert: this seeder must be re-runnable and
            // must not collide with ClubRolesAndPermissionsSeeder or the
            // per-role user seeders, which create some of these too.
            DB::table('roles')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'slug' => $name,
                    'permissions' => json_encode(['label' => $label]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
