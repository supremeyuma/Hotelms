<?php

namespace Tests\Feature\Staff;

use App\Models\StaffThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffThreadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_only_their_own_threads(): void
    {
        $staffRole = Role::create([
            'name' => 'staff',
            'slug' => 'staff',
            'guard_name' => 'web',
        ]);

        $staff = User::factory()->create([
            'name' => 'Ada Staff',
        ]);
        $staff->assignRole($staffRole);

        $otherStaff = User::factory()->create([
            'name' => 'Bola Staff',
        ]);
        $otherStaff->assignRole($staffRole);

        $ownThread = StaffThread::create([
            'staff_id' => $staff->id,
            'type' => 'query',
            'title' => 'Ada question',
        ]);
        $ownThread->messages()->create([
            'sender_id' => $staff->id,
            'message' => 'I need help with shift coverage.',
            'attachments' => [],
        ]);

        $otherThread = StaffThread::create([
            'staff_id' => $otherStaff->id,
            'type' => 'commendation',
            'title' => 'Bola commendation',
        ]);
        $otherThread->messages()->create([
            'sender_id' => $otherStaff->id,
            'message' => 'Great guest feedback received.',
            'attachments' => [],
        ]);

        $indexResponse = $this->actingAs($staff)->get(route('staff.threads.index'));

        $indexResponse->assertOk();
        $indexResponse->assertInertia(fn ($page) => $page
            ->component('Staff/Threads/Index')
            ->has('threads.data', 1)
            ->where('threads.data.0.title', 'Ada question')
        );

        $showResponse = $this->actingAs($staff)->get(route('staff.threads.show', $ownThread));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn ($page) => $page
            ->component('Staff/Threads/Show')
            ->where('thread.title', 'Ada question')
        );

        $forbiddenResponse = $this->actingAs($staff)->get(route('staff.threads.show', $otherThread));
        $forbiddenResponse->assertForbidden();
    }
}
