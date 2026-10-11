<?php

namespace Tests\Feature\ClubPos;

use App\Models\Booking;
use App\Models\MenuItem;
use App\Models\PosDevice;
use App\Models\PosDocket;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private PosSession $session;
    private MenuItem $item;

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

        $this->item = MenuItem::factory()->create([
            'price' => 1000,
            'is_available' => true,
        ]);
    }

    public function test_a_docket_can_be_opened(): void
    {
        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $this->session->id])
            ->assertCreated();

        $this->assertDatabaseHas('pos_dockets', [
            'session_id' => $this->session->id,
            'status' => 'open',
        ]);
    }

    public function test_an_item_can_be_added_and_the_subtotal_recalculates(): void
    {
        $docketId = $this->openDocket();

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 2],
        )->assertOk();

        $this->assertDatabaseHas('pos_docket_items', [
            'docket_id' => $docketId,
            'menu_item_id' => $this->item->id,
            'quantity' => 2,
        ]);

        $this->assertSame(2000.0, (float) PosDocket::find($docketId)->subtotal);
    }

    public function test_adding_an_item_to_a_closed_docket_is_refused(): void
    {
        $docketId = $this->openDocket();

        PosDocket::find($docketId)->update(['status' => 'closed']);

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $docketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 1],
        )->assertStatus(422);
    }

    public function test_a_room_charge_posts_to_the_guest_folio(): void
    {
        $booking = Booking::factory()->create();

        $docketId = $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), [
                'session_id' => $this->session->id,
                'booking_id' => $booking->id,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'room_charge', 'amount' => 2000]]],
        )->assertOk();

        $this->assertDatabaseHas('charges', ['booking_id' => $booking->id]);
    }

    public function test_a_paid_docket_cannot_be_voided(): void
    {
        $docketId = $this->openDocket();

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.pay', $docketId),
            ['payments' => [['method' => 'cash', 'amount' => 0]]],
        )->assertOk();

        $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.void', $docketId), ['reason' => 'mistake'])
            ->assertStatus(422);
    }

    public function test_inertia_docket_actions_return_a_redirect_not_json(): void
    {
        $this->actingAs($this->staff)
            ->post(route('club.pos.dockets.store'), ['session_id' => $this->session->id], ['X-Inertia' => 'true'])
            ->assertStatus(302);

        $docketId = $this->openDocket();

        $this->actingAs($this->staff)
            ->post(route('club.pos.dockets.items.store', $docketId), [
                'menu_item_id' => $this->item->id,
                'quantity' => 1,
            ], ['X-Inertia' => 'true'])
            ->assertStatus(302);
    }

    public function test_a_docket_item_cannot_be_removed_from_a_different_docket(): void
    {
        $otherDocketId = $this->openDocket();

        $this->actingAs($this->staff)->postJson(
            route('club.pos.dockets.items.store', $otherDocketId),
            ['menu_item_id' => $this->item->id, 'quantity' => 1],
        )->assertOk();

        $otherItemId = PosDocket::find($otherDocketId)->items()->first()->id;

        $targetDocketId = $this->openDocket();

        $this->actingAs($this->staff)
            ->deleteJson(route('club.pos.dockets.items.destroy', [
                'docket' => $targetDocketId,
                'item' => $otherItemId,
            ]))
            ->assertNotFound();
    }

    public function test_a_docket_auto_opens_a_shift_when_none_is_supplied(): void
    {
        $fresh = User::factory()->create();
        $fresh->assignRole('club_staff');

        $this->actingAs($fresh)
            ->postJson(route('club.pos.dockets.store'), [])
            ->assertCreated();

        $this->assertDatabaseHas('pos_sessions', [
            'user_id' => $fresh->id,
            'status' => 'open',
        ]);
    }

    private function openDocket(): string
    {
        return $this->actingAs($this->staff)
            ->postJson(route('club.pos.dockets.store'), ['session_id' => $this->session->id])
            ->assertCreated()
            ->json('id');
    }
}