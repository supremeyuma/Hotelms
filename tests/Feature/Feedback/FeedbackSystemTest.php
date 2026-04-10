<?php

namespace Tests\Feature\Feedback;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeedbackSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_anonymous_feedback_submission_does_not_store_identity(): void
    {
        $response = $this->post(route('feedback.store'), [
            'category' => 'complaint',
            'subject' => 'Noise late at night',
            'message' => 'There was loud noise in the corridor after midnight.',
            'rating' => 2,
            'is_anonymous' => true,
            'allow_follow_up' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('feedback', [
            'source' => Feedback::SOURCE_PUBLIC,
            'category' => 'complaint',
            'subject' => 'Noise late at night',
            'is_anonymous' => true,
            'submitted_by_user_id' => null,
            'booking_id' => null,
            'room_id' => null,
            'contact_name' => null,
            'contact_email' => null,
        ]);
    }

    public function test_staff_can_submit_named_feedback_with_their_identity_attached(): void
    {
        $staff = $this->staffUser();

        $response = $this->actingAs($staff)->post(route('staff.feedback.store'), [
            'category' => 'suggestion',
            'subject' => 'Housekeeping handoff',
            'message' => 'A short evening handoff checklist would reduce missed tasks.',
            'rating' => 4,
            'is_anonymous' => false,
            'allow_follow_up' => true,
            'contact_name' => '',
            'contact_email' => '',
            'contact_phone' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('feedback', [
            'source' => Feedback::SOURCE_STAFF,
            'category' => 'suggestion',
            'submitted_by_user_id' => $staff->id,
            'contact_name' => $staff->name,
            'contact_email' => $staff->email,
            'allow_follow_up' => true,
            'is_anonymous' => false,
        ]);
    }

    public function test_manager_can_view_and_update_feedback_queue(): void
    {
        $manager = $this->managerUser();

        $feedback = Feedback::create([
            'source' => Feedback::SOURCE_PUBLIC,
            'category' => 'service',
            'status' => Feedback::STATUS_NEW,
            'subject' => 'Front desk response',
            'message' => 'The response time was excellent.',
            'is_anonymous' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('admin.feedback.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Feedback/Index')
            ->has('feedback.data', 1)
            ->where('feedback.data.0.subject', 'Front desk response')
        );

        $updateResponse = $this->actingAs($manager)->patch(route('admin.feedback.update', $feedback), [
            'status' => Feedback::STATUS_IN_REVIEW,
            'internal_notes' => 'Flagged for discussion with front office lead.',
        ]);

        $updateResponse->assertRedirect();

        $this->assertDatabaseHas('feedback', [
            'id' => $feedback->id,
            'status' => Feedback::STATUS_IN_REVIEW,
            'reviewed_by_user_id' => $manager->id,
            'internal_notes' => 'Flagged for discussion with front office lead.',
        ]);
    }

    private function staffUser(): User
    {
        $role = Role::firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ], [
            'slug' => 'staff',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function managerUser(): User
    {
        $role = Role::firstOrCreate([
            'name' => 'manager',
            'guard_name' => 'web',
        ], [
            'slug' => 'manager',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
