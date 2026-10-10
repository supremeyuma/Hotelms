<?php

namespace Tests\Feature\ClubPos;

use App\Models\PosDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosDeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(["name" => "club_staff", "slug" => "club_staff", "guard_name" => "web"]);
        $permission = Permission::create(["name" => "club.pos.access", "slug" => "club.pos.access", "guard_name" => "web"]);
        $role->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);
    }

    public function test_a_device_is_registered_on_first_pos_access(): void
    {
        $this->assertSame(0, PosDevice::count());

        $response = $this->actingAs($this->staff)
            ->get(route('club.pos.dashboard'))
            ->assertOk();

        $this->assertSame(1, PosDevice::count());

        $cookieName = config('pos.devices.identity_cookie');
        $cookies = collect($response->headers->getCookies())->keyBy->getName();
        $this->assertTrue($cookies->has($cookieName));
        $this->assertDatabaseHas('pos_devices', ['device_code' => $cookies->get($cookieName)->getValue()]);
    }

    public function test_repeat_access_does_not_create_duplicate_devices(): void
    {
        $cookieName = config('pos.devices.identity_cookie');

        $first = $this->actingAs($this->staff)->get(route('club.pos.dashboard'))->assertOk();
        $code = collect($first->headers->getCookies())->keyBy->getName()->get($cookieName)->getValue();

        $this->actingAs($this->staff)
            ->withUnencryptedCookies([$cookieName => $code])
            ->get(route('club.pos.dashboard'))
            ->assertOk();

        $this->assertSame(1, PosDevice::count());
        $this->assertSame(1, PosDevice::where('device_code', $code)->count());
    }

    public function test_auto_registration_can_be_disabled_via_config(): void
    {
        config(['pos.devices.auto_register' => false]);

        $response = $this->actingAs($this->staff)->get(route('club.pos.dashboard'))->assertOk();

        $this->assertSame(1, PosDevice::count());
        $this->assertDatabaseHas('pos_devices', ['device_code' => config('pos.devices.default.code')]);
        $this->assertFalse(collect($response->headers->getCookies())->keyBy->getName()->has(config('pos.devices.identity_cookie')));
    }
}