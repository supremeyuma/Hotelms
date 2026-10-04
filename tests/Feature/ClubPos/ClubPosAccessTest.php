<?php

namespace Tests\Feature\ClubPos;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClubPosAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get("/club/pos");
        $response->assertRedirect("/login");
    }

    public function test_dashboard_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->get("/club/pos");
        $response->assertStatus(403);
    }

    public function test_dashboard_loads_with_permission(): void
    {
        $role = Role::create(["name" => "club_staff", "slug" => "club_staff", "guard_name" => "web"]);
        $perm = Permission::create(["name" => "club.pos.access", "slug" => "club.pos.access", "guard_name" => "web"]);
        $role->givePermissionTo($perm);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        $response = $this->get("/club/pos");
        $response->assertStatus(200);
    }
}
