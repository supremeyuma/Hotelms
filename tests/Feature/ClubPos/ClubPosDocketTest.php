<?php

namespace Tests\Feature\ClubPos;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClubPosDocketTest extends TestCase
{
    use RefreshDatabase;

    protected function setupUser(): User
    {
        $role = Role::create(["name" => "club_staff", "slug" => "club_staff", "guard_name" => "web"]);
        $perm = Permission::create(["name" => "club.pos.access", "slug" => "club.pos.access", "guard_name" => "web"]);
        $role->givePermissionTo($perm);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_can_open_docket_without_session_id(): void
    {
        $user = $this->setupUser();
        $this->actingAs($user);
        $response = $this->postJson("/club/pos/dockets", []);
        $response->assertStatus(201);
    }
}
