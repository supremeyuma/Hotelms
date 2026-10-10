<?php

namespace Tests\Feature\ClubPos;

use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PosSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'club_staff', 'slug' => 'club_staff', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'club.pos.access', 'slug' => 'club.pos.access', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);
    }

    public function test_a_shift_can_be_opened(): void
    {
        $device = PosDevice::factory()->create();

        $response = $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.open'), ['device_id' => $device->id]);

        $response->assertCreated();

        $this->assertDatabaseHas('pos_sessions', [
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
            'status' => 'open',
        ]);
    }

    public function test_opening_a_second_shift_returns_the_same_open_session(): void
    {
        $device = PosDevice::factory()->create();

        $first = $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.open'), ['device_id' => $device->id])
            ->assertCreated();

        $second = $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.open'), ['device_id' => $device->id])
            ->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, PosSession::count());
    }

    public function test_a_shift_can_be_closed_and_reconciled(): void
    {
        $device = PosDevice::factory()->create();

        $session = PosSession::factory()->create([
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
            'cash_start' => 1000,
        ]);

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.close', $session), ['cash_declared' => 1000])
            ->assertOk();

        $closed = $session->fresh();
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('closed', $closed->status);
        $this->assertEqualsWithDelta(0, (float) $closed->cash_variance, 0.01);

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.shifts.reconcile', $session), [
                'cash_verified' => 900,
                'notes' => 'Short by 100',
            ])
            ->assertOk();

        $reconciled = $session->fresh();
        $this->assertSame('900.00', $reconciled->cash_verified);
        $this->assertEqualsWithDelta(-100, (float) $reconciled->cash_variance, 0.01);
    }

    public function test_the_current_shift_endpoint_returns_the_open_session(): void
    {
        $device = PosDevice::factory()->create();

        $session = PosSession::factory()->create([
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->getJson(route('club.pos.shifts.current'));

        $response->assertOk();
        $this->assertSame($session->id, $response->json('id'));
    }
}