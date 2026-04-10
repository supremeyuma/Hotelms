<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_staff_directory_from_admin_area(): void
    {
        $managerRole = Role::create([
            'name' => 'manager',
            'slug' => 'manager',
            'guard_name' => 'web',
        ]);

        $staffRole = Role::create([
            'name' => 'staff',
            'slug' => 'staff',
            'guard_name' => 'web',
        ]);

        $manager = User::factory()->create([
            'name' => 'Zed Manager',
        ]);
        $manager->assignRole($managerRole);

        $staffMember = User::factory()->create([
            'name' => 'Ada Staff',
        ]);
        $staffMember->assignRole($staffRole);

        $response = $this->actingAs($manager)->get(route('admin.staff.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Staff/Index')
            ->where('routePrefix', 'admin.staff')
            ->has('staff.data', 2)
            ->where('staff.data.0.name', 'Ada Staff')
        );
    }
}
