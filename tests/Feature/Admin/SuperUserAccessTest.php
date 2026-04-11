<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superuser_can_open_md_protected_admin_pages(): void
    {
        $superUserRole = Role::create([
            'name' => 'superuser',
            'slug' => 'superuser',
            'guard_name' => 'web',
        ]);

        $mdRole = Role::create([
            'name' => 'md',
            'slug' => 'md',
            'guard_name' => 'web',
        ]);

        $managerRole = Role::create([
            'name' => 'manager',
            'slug' => 'manager',
            'guard_name' => 'web',
        ]);

        $superUser = User::factory()->create([
            'name' => 'Alex Superuser',
            'role_id' => $mdRole->id,
        ]);
        $superUser->syncRoles([$superUserRole->name, $mdRole->name]);

        $manager = User::factory()->create([
            'name' => 'Maya Manager',
        ]);
        $manager->assignRole($managerRole);

        $response = $this->actingAs($superUser)->get(route('admin.staff.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Staff/Index')
            ->where('routePrefix', 'admin.staff')
        );
    }
}
