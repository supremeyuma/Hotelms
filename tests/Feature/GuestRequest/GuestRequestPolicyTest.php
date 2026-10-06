<?php

namespace Tests\Feature\GuestRequest;

use App\Models\GuestRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Authorization rules confirmed 2026-10-05:
 *   - viewAny / view / create / update / acknowledge / resolve => frontdesk + md
 *   - delete                                                   => md only
 *   - every other role                                          => denied
 *
 * Ownership: a guest request belongs to one booking (booking_id + room_id,
 * both non-null). Front desk sees every booking, so there is no per-user
 * scoping on the collection.
 */
class GuestRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
            ['slug' => $roleName]
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function guestRequest(string $status = 'pending'): GuestRequest
    {
        return GuestRequest::factory()->create(['status' => $status]);
    }

    /** @return string[] */
    protected function nonAuthorisedRoles(): array
    {
        return ['manager', 'staff', 'clean', 'laundry', 'kitchen', 'bar', 'accountant', 'guest'];
    }

    // ------------------------------------------------------------------
    // md — full access
    // ------------------------------------------------------------------

    public function test_md_may_view_any(): void
    {
        $md = $this->userWithRole('md');

        $this->assertTrue($md->can('viewAny', GuestRequest::class));
    }

    public function test_md_may_view_a_request(): void
    {
        $md = $this->userWithRole('md');
        $request = $this->guestRequest();

        $this->assertTrue($md->can('view', $request));
    }

    public function test_md_may_create_a_request(): void
    {
        $md = $this->userWithRole('md');

        $this->assertTrue($md->can('create', GuestRequest::class));
    }

    public function test_md_may_update_a_request(): void
    {
        $md = $this->userWithRole('md');
        $request = $this->guestRequest();

        $this->assertTrue($md->can('update', $request));
    }

    public function test_md_may_acknowledge_a_request(): void
    {
        $md = $this->userWithRole('md');
        $request = $this->guestRequest();

        $this->assertTrue($md->can('acknowledge', $request));
    }

    public function test_md_may_resolve_a_request(): void
    {
        $md = $this->userWithRole('md');
        $request = $this->guestRequest();

        $this->assertTrue($md->can('resolve', $request));
    }

    public function test_md_may_delete_a_request(): void
    {
        $md = $this->userWithRole('md');
        $request = $this->guestRequest();

        $this->assertTrue($md->can('delete', $request));
    }

    // ------------------------------------------------------------------
    // frontdesk — full access except delete
    // ------------------------------------------------------------------

    public function test_frontdesk_may_view_any(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');

        $this->assertTrue($frontdesk->can('viewAny', GuestRequest::class));
    }

    public function test_frontdesk_may_view_a_request(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');
        $request = $this->guestRequest();

        $this->assertTrue($frontdesk->can('view', $request));
    }

    public function test_frontdesk_may_create_a_request(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');

        $this->assertTrue($frontdesk->can('create', GuestRequest::class));
    }

    public function test_frontdesk_may_update_a_request(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');
        $request = $this->guestRequest();

        $this->assertTrue($frontdesk->can('update', $request));
    }

    public function test_frontdesk_may_acknowledge_a_request(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');
        $request = $this->guestRequest();

        $this->assertTrue($frontdesk->can('acknowledge', $request));
    }

    public function test_frontdesk_may_resolve_a_request(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');
        $request = $this->guestRequest();

        $this->assertTrue($frontdesk->can('resolve', $request));
    }

    public function test_frontdesk_may_not_delete_a_request(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');
        $request = $this->guestRequest();

        $this->assertFalse($frontdesk->can('delete', $request));
    }

    // ------------------------------------------------------------------
    // Every other role is denied. These are the assertions that matter.
    // ------------------------------------------------------------------

    public function test_unauthorised_roles_are_denied_every_ability(): void
    {
        $request = $this->guestRequest();

        foreach ($this->nonAuthorisedRoles() as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->assertFalse(
                $user->can('viewAny', GuestRequest::class),
                "{$roleName} must not be able to viewAny GuestRequest"
            );
            $this->assertFalse(
                $user->can('view', $request),
                "{$roleName} must not be able to view a GuestRequest"
            );
            $this->assertFalse(
                $user->can('create', GuestRequest::class),
                "{$roleName} must not be able to create a GuestRequest"
            );
            $this->assertFalse(
                $user->can('update', $request),
                "{$roleName} must not be able to update a GuestRequest"
            );
            $this->assertFalse(
                $user->can('acknowledge', $request),
                "{$roleName} must not be able to acknowledge a GuestRequest"
            );
            $this->assertFalse(
                $user->can('resolve', $request),
                "{$roleName} must not be able to resolve a GuestRequest"
            );
            $this->assertFalse(
                $user->can('delete', $request),
                "{$roleName} must not be able to delete a GuestRequest"
            );
        }
    }

    // ------------------------------------------------------------------
    // The controller enforces the policy, not just the policy object.
    // ------------------------------------------------------------------

    public function test_the_index_route_forbids_a_user_without_the_role(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get('/frontdesk/guest-requests')
            ->assertForbidden();
    }

    public function test_the_index_route_forbids_a_housekeeping_user(): void
    {
        $clean = $this->userWithRole('clean');

        $this->actingAs($clean)
            ->get('/frontdesk/guest-requests')
            ->assertForbidden();
    }

    public function test_the_index_route_forbids_an_unauthenticated_user(): void
    {
        $this->get('/frontdesk/guest-requests')->assertRedirect();
    }

    public function test_the_acknowledge_route_forbids_a_user_without_the_role(): void
    {
        $staff = $this->userWithRole('staff');
        $request = $this->guestRequest('pending');

        $this->actingAs($staff)
            ->post("/frontdesk/guest-requests/{$request->id}/acknowledge")
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_the_complete_route_forbids_a_user_without_the_role(): void
    {
        $staff = $this->userWithRole('staff');
        $request = $this->guestRequest('pending');

        $this->actingAs($staff)
            ->post("/frontdesk/guest-requests/{$request->id}/complete")
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_the_index_route_allows_a_frontdesk_user(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');

        $this->actingAs($frontdesk)
            ->get('/frontdesk/guest-requests')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('FrontDesk/GuestRequests/Index'));
    }

    public function test_the_acknowledge_route_allows_a_frontdesk_user(): void
    {
        $frontdesk = $this->userWithRole('frontdesk');
        $request = $this->guestRequest('pending');

        $this->actingAs($frontdesk)
            ->from('/frontdesk/guest-requests')
            ->post("/frontdesk/guest-requests/{$request->id}/acknowledge")
            ->assertRedirect('/frontdesk/guest-requests');

        $this->assertSame('acknowledged', $request->fresh()->status);
    }

    public function test_the_complete_route_allows_an_md_user(): void
    {
        $md = $this->userWithRole('md');
        $request = $this->guestRequest('pending');

        $this->actingAs($md)
            ->from('/frontdesk/guest-requests')
            ->post("/frontdesk/guest-requests/{$request->id}/complete")
            ->assertRedirect('/frontdesk/guest-requests');

        $this->assertSame('completed', $request->fresh()->status);
    }
}
