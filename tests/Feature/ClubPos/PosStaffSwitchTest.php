<?php

namespace Tests\Feature\ClubPos;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosStaffSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function clubUser(string $actionCode): User
    {
        $role = Role::create(['name' => 'club_staff', 'slug' => 'club_staff', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'club.pos.access', 'slug' => 'club.pos.access', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $user = User::factory()->create();
        $user->assignRole($role);

        $profile = $user->staffProfile()->create(['position' => 'Bartender']);
        $profile->storeActionCode($actionCode);
        $profile->save();

        return $user;
    }

    public function test_a_valid_pin_switches_staff_and_marks_the_session_verified(): void
    {
        $staff = $this->clubUser('1234');
        $operator = User::factory()->create();
        $this->actingAs($operator);

        $response = $this->postJson('/pos/staff/switch', ['pin' => '1234']);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertTrue(session('pos_pin_verified'));
        $this->assertAuthenticatedAs($staff);
    }

    public function test_an_invalid_pin_is_rejected(): void
    {
        $this->clubUser('1234');
        $operator = User::factory()->create();
        $this->actingAs($operator);

        $response = $this->postJson('/pos/staff/switch', ['pin' => '0000']);

        $response->assertStatus(422)->assertJson(['error' => 'Invalid PIN.']);
        $this->assertAuthenticatedAs($operator);
        $this->assertNull(session('pos_pin_verified'));
    }

    public function test_the_dashboard_shares_the_pin_verified_flag(): void
    {
        $user = $this->clubUser('1234');

        $response = $this->actingAs($user)
            ->withSession(['pos_pin_verified' => true])
            ->get('/club/pos');

        $response->assertOk();
        $page = $response->viewData('page');
        $this->assertIsArray($page);
        $this->assertTrue($page['props']['club_pos']['pin_verified']);
    }

    public function test_the_dashboard_reports_pin_not_verified_by_default(): void
    {
        $user = $this->clubUser('1234');

        $response = $this->actingAs($user)->get('/club/pos');

        $response->assertOk();
        $page = $response->viewData('page');
        $this->assertFalse($page['props']['club_pos']['pin_verified']);
    }
}
