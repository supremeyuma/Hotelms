<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffDeletionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_md_can_delete_staff_record(): void
    {
        $mdRole = Role::create([
            'name' => 'md',
            'slug' => 'md',
            'guard_name' => 'web',
        ]);

        $staffRole = Role::create([
            'name' => 'staff',
            'slug' => 'staff',
            'guard_name' => 'web',
        ]);

        $md = User::factory()->create();
        $md->assignRole($mdRole);

        $staff = User::factory()->create();
        $staff->assignRole($staffRole);

        $response = $this->actingAs($md)->delete(route('admin.staff.destroy', $staff));

        $response->assertRedirect(route('admin.staff.index'));
        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }

    public function test_superuser_can_delete_staff_record(): void
    {
        $superUserRole = Role::create([
            'name' => 'superuser',
            'slug' => 'superuser',
            'guard_name' => 'web',
        ]);

        $staffRole = Role::create([
            'name' => 'staff',
            'slug' => 'staff',
            'guard_name' => 'web',
        ]);

        $superUser = User::factory()->create();
        $superUser->assignRole($superUserRole);

        $staff = User::factory()->create();
        $staff->assignRole($staffRole);

        $response = $this->actingAs($superUser)->delete(route('admin.staff.destroy', $staff));

        $response->assertRedirect(route('admin.staff.index'));
        $this->assertSoftDeleted('users', ['id' => $staff->id]);
    }
}
