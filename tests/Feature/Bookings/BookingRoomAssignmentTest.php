<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 5 Task 1: booking_rooms is the single room-assignment writer and the
 * legacy bookings.room_id scalar follows it.
 */
class BookingRoomAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassigning_a_room_updates_the_pivot_not_only_the_legacy_scalar(): void
    {
        [$property, $roomType, $room] = $this->roomFixture('Alpha 101');
        $newRoom = $this->roomFixture('Beta 202', $property, $roomType)[2];

        $booking = $this->bookingFixture($property, $roomType, $room);

        $this->actingAs($this->managerUser())
            ->post(route('admin.bookings.reassign-room', $booking), ['room_id' => $newRoom->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('booking_rooms', [
            'booking_id' => $booking->id,
            'room_id'    => $newRoom->id,
        ]);
        $this->assertDatabaseMissing('booking_rooms', [
            'booking_id' => $booking->id,
            'room_id'    => $room->id,
        ]);

        $this->assertSame($newRoom->id, (int) $booking->refresh()->room_id);
    }

    public function test_removing_a_room_releases_it(): void
    {
        [$property, $roomType, $roomA] = $this->roomFixture('Alpha 101');
        $roomB = $this->roomFixture('Beta 202', $property, $roomType)[2];
        $roomB->update(['status' => 'reserved']);

        $booking = $this->bookingFixture($property, $roomType, $roomA, [$roomA, $roomB]);

        app(BookingService::class)->assignRooms($booking, [$roomA->id]);
        $booking->refresh()->load('rooms');

        $this->assertSame([$roomA->id], $booking->rooms->pluck('id')->all());
        $this->assertSame(1, (int) $booking->quantity);
        $this->assertSame($roomA->id, (int) $booking->room_id);
        $this->assertSame('available', $roomB->refresh()->status);
    }

    public function test_assigning_rooms_keeps_the_legacy_scalar_in_sync(): void
    {
        [$property, $roomType, $roomA] = $this->roomFixture('Alpha 101');
        $roomB = $this->roomFixture('Beta 202', $property, $roomType)[2];

        $booking = $this->bookingFixture($property, $roomType, $roomA);

        app(BookingService::class)->assignRooms($booking, [$roomB->id, $roomA->id]);
        $booking->refresh()->load('rooms');

        $this->assertSame($roomB->id, (int) $booking->room_id);
        $this->assertSame(2, (int) $booking->quantity);
        $this->assertSame([$roomA->id, $roomB->id], $booking->rooms->pluck('id')->sort()->values()->all());
    }

    public function test_assigning_rooms_preserves_pivot_state_of_retained_rooms(): void
    {
        [$property, $roomType, $roomA] = $this->roomFixture('Alpha 101');

        $booking = $this->bookingFixture($property, $roomType, $roomA);
        $booking->rooms()->updateExistingPivot($roomA->id, [
            'status' => 'active',
            'checked_in_at' => now()->subDay(),
        ]);

        app(BookingService::class)->assignRooms($booking, [$roomA->id]);

        $pivot = $booking->refresh()->rooms()->first()->pivot;

        $this->assertSame('active', $pivot->status);
        $this->assertNotNull($pivot->checked_in_at);
    }

    public function test_pivot_status_is_readable_through_the_rooms_relation(): void
    {
        [$property, $roomType, $room] = $this->roomFixture('Alpha 101');

        $booking = $this->bookingFixture($property, $roomType, $room);
        $booking->rooms()->updateExistingPivot($room->id, ['status' => 'active']);

        $this->assertSame('active', $booking->refresh()->rooms->first()->pivot->status);
    }

    protected function roomFixture(string $name, ?Property $property = null, ?RoomType $roomType = null): array
    {
        $property ??= Property::create([
            'name' => 'Summit Hotel',
            'location' => 'Abuja',
            'amenities' => [],
        ]);

        $roomType ??= RoomType::create([
            'property_id' => $property->id,
            'title' => 'Deluxe',
            'max_occupancy' => 2,
            'base_price' => 65000,
            'features' => [],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => $name,
            'code' => 'RM-' . str_replace(' ', '-', $name),
            'status' => 'available',
            'meta' => [],
        ]);

        return [$property, $roomType, $room];
    }

    protected function bookingFixture(Property $property, RoomType $roomType, Room $room, ?array $rooms = null): Booking
    {
        $rooms ??= [$room];

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'booking_code' => 'ASG-' . strtoupper(bin2hex(random_bytes(4))),
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'guests' => 2,
            'adults' => 2,
            'children' => 0,
            'quantity' => count($rooms),
            'nightly_rate' => 65000,
            'total_amount' => 65000 * count($rooms) * 2,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'guest_name' => 'Miriam Okoro',
            'guest_email' => 'miriam@example.com',
            'guest_phone' => '08030000001',
        ]);

        $booking->rooms()->attach(
            collect($rooms)->mapWithKeys(fn (Room $r) => [$r->id => ['status' => 'reserved']])->all()
        );

        return $booking;
    }

    protected function managerUser(): User
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
