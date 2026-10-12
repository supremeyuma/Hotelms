<?php

namespace Tests\Feature\Offline;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConnectivityTest extends TestCase
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

    public function test_connectivity_is_reported_as_online_when_sync_is_disabled(): void
    {
        config(['sync.enabled' => false]);

        $this->actingAs($this->staff)
            ->getJson(route('club.pos.connectivity'))
            ->assertOk()
            ->assertJson(['online' => true, 'peers' => 0, 'failed' => []]);
    }

    public function test_connectivity_is_reported_as_online_when_sync_is_enabled_with_no_peers(): void
    {
        config(['sync.enabled' => true, 'sync.peers' => []]);

        $this->actingAs($this->staff)
            ->getJson(route('club.pos.connectivity'))
            ->assertOk()
            ->assertJson(['online' => true, 'peers' => 0, 'failed' => []]);
    }

    public function test_connectivity_is_reported_as_offline_when_every_peer_fails(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
            'sync.peers' => ['http://unreachable.test'],
        ]);

        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->actingAs($this->staff)
            ->getJson(route('club.pos.connectivity', ['probe' => 1]))
            ->assertOk()
            ->assertJson(['online' => false, 'peers' => 1]);
    }

    public function test_connectivity_is_reported_as_online_when_any_peer_succeeds(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
            'sync.peers' => ['http://lan.test', 'http://cloud.test'],
        ]);

        Http::fake([
            'lan.test/*' => fn () => throw new ConnectionException('refused'),
            'cloud.test/*' => Http::response(['accepted' => 0, 'changes' => [], 'cursor' => 0]),
        ]);

        $this->actingAs($this->staff)
            ->getJson(route('club.pos.connectivity', ['probe' => 1]))
            ->assertOk()
            ->assertJson(['online' => true, 'peers' => 2]);
    }

    public function test_the_connectivity_endpoint_requires_authentication(): void
    {
        $this->getJson(route('club.pos.connectivity'))->assertUnauthorized();
    }
}
