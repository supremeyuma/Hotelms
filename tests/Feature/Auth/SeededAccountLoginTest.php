<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the release blocker fixed alongside this test: a fresh `db:seed` used to
 * create accounts whose Spatie roles did not satisfy the `role:` middleware on
 * their own dashboards, so a new installation could not be operated at all.
 *
 * The accounts below are the ones an installer or a demo run is expected to
 * produce. Each must exist, carry a role, and reach its landing route.
 */
class SeededAccountLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * email => landing route, with the seeded password. Route names and their
     * middleware were read from `php artisan route:list`, not assumed.
     */
    private function matrix(): array
    {
        return [
            ['superuser@hotelms.com', 'superuser@!234', 'admin.dashboard'],
            ['ceo@email.com', '1234', 'admin.dashboard'],
            ['manager@email.com', 'ManagerPass1!', 'admin.dashboard'],
            ['frontdesk@email.com', '11111111', 'frontdesk.dashboard'],
            ['kitchen@email.com', '11111111', 'staff.kitchen.dashboard'],
            ['bar@email.com', '11111111', 'staff.bar.dashboard'],
            ['clean@email.com', '11111111', 'clean.dashboard'],
            ['laundry@email.com', '11111111', 'staff.laundry.dashboard'],
            ['staff@email.com', '11111111', 'staff.dashboard'],
            ['hr@email.com', '11111111', 'hr.staff.index'],
            ['account@email.com', '11111111', 'finance.dashboard'],
        ];
    }

    public function test_every_operational_role_the_routes_reference_exists(): void
    {
        $this->seed();

        // Derived from the `role:` middleware across routes/*.php. If a route
        // starts referencing a new role, this list is the reminder to seed it.
        $expected = [
            'superuser', 'md', 'manager', 'frontdesk', 'staff', 'kitchen',
            'bar', 'clean', 'laundry', 'inventory', 'accountant', 'hr', 'guest',
        ];

        foreach ($expected as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_every_seeded_account_has_a_spatie_role(): void
    {
        $this->seed();

        foreach ($this->matrix() as [$email, , $route]) {
            $user = User::where('email', $email)->first();

            $this->assertNotNull($user, "{$email} was not seeded");
            $this->assertTrue(
                $user->roles()->exists(),
                "{$email} has no Spatie role, so the role: middleware on {$route} rejects it",
            );
        }
    }

    public function test_every_seeded_account_reaches_its_landing_route(): void
    {
        $this->seed();

        foreach ($this->matrix() as [$email, $password, $route]) {
            $user = User::where('email', $email)->firstOrFail();

            // Verifies the seeded hash, not just a mocked Auth::actingAs.
            $this->assertTrue(
                \Illuminate\Support\Facades\Hash::check($password, $user->password),
                "seeded password for {$email} does not match",
            );

            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    /**
     * The three seeders that still read the legacy `users.role_id` join on
     * `roles.slug`. Fixing the Spatie pivot must not silently empty that column,
     * or BookingsSeeder/OrdersSeeder/StaffProfilesSeeder produce no rows.
     */
    public function test_legacy_role_id_stays_populated_for_the_seeders_that_read_it(): void
    {
        $this->seed();

        $this->assertGreaterThan(
            0,
            \DB::table('users')->whereIn('role_id', function ($q) {
                $q->select('id')->from('roles')->where('slug', 'guest');
            })->count(),
            'BookingsSeeder finds guests by roles.slug=guest via users.role_id',
        );

        $this->assertGreaterThan(
            0,
            \DB::table('users')->whereIn('role_id', function ($q) {
                $q->select('id')->from('roles')->where('slug', 'staff');
            })->count(),
            'StaffProfilesSeeder finds staff by roles.slug=staff via users.role_id',
        );
    }
}
