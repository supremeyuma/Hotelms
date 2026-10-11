<?php

namespace Tests\Feature\ClubPos;

use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocketBookingAttachTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private PosSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'club_staff', 'slug' => 'club_staff', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'club.pos.access', 'slug' => 'club.pos.access', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);

        $device = PosDevice::factory()->create();

        $this->session = PosSession::factory()->create([
            'device_id' => $device->id,
            'user_id' => $this->staff->id,
        ]);
    }

    public function test_a_booking_can_be_attached_to_an_open_docket(): void
    {
        $booking = Booking::factory()->create(['status' => 'checked_in']);
        $docketId = $this->openDocket();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.booking', $docketId), ['booking_id' => $booking->id])
            ->assertOk();

        $this->assertDatabaseHas('pos_dockets', [
            'id' => $docketId,
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
        ]);
    }

    public function test_a_booking_cannot_be_attached_to_a_closed_docket(): void
    {
        $booking = Booking::factory()->create(['status' => 'checked_in']);
        $docketId = $this->openDocket();

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'cash', 'amount' => 0]]],
        )->assertOk();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.booking', $docketId), ['booking_id' => $booking->id])
            ->assertStatus(422);
    }

    public function test_booking_search_returns_active_bookings_only(): void
    {
        $active = Booking::factory()->create(['status' => 'checked_in', 'guest_name' => 'Ada Lovelace']);
        Booking::factory()->create(['status' => 'cancelled', 'guest_name' => 'Ada Lovelace']);

        $response = $this->actingAs($this->staff)
            ->getJson(route('club.pos.bookings.search', ['q' => 'Ada']))
            ->assertOk();

        $ids = collect($response->json('results'))->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_room_charge_after_attaching_a_booking_posts_to_the_folio(): void
    {
        $booking = Booking::factory()->create(['status' => 'checked_in']);
        $docketId = $this->openDocket();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.booking', $docketId), ['booking_id' => $booking->id])
            ->assertOk();

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'room_charge', 'amount' => 2000]]],
        )->assertOk();

        $this->assertDatabaseHas('charges', ['booking_id' => $booking->id]);
    }

    private function openDocket(): string
    {
        return $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $this->session->id])
            ->assertCreated()
            ->json('id');
    }
}
