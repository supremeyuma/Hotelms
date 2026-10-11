<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrontDeskAvailabilityRecheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_front_desk_cannot_assign_a_room_already_reserved_for_those_dates(): void
    {
        $room = $this->roomFixture();
        $this->bookingFixture($room, now()->addDay(), now()->addDays(3));

        $this->actingAs($this->frontdeskUser())
            ->postJson('/frontdesk/bookings', [
                'guest_name' => 'Walk In',
                'guest_email' => 'walkin@example.test',
                'selected_room_ids' => [$room->id],
                'check_in' => now()->addDay()->toDateString(),
                'check_out' => now()->addDays(2)->toDateString(),
                'adults' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('selected_room_ids');
    }

    public function test_a_stay_ending_the_day_another_begins_is_not_a_conflict(): void
    {
        $room = $this->roomFixture();
        // Arrival today, checkout tomorrow; the next stay arrives the same day the
        // first one leaves. Half-open overlap: not a conflict. Both arrivals are
        // inside the local window, so the cutoff guard is not what is under test.
        $this->bookingFixture($room, now(), now()->addDay());

        $response = $this->actingAs($this->frontdeskUser())
            ->post('/frontdesk/bookings', [
                'guest_name' => 'Next Guest',
                'guest_email' => 'next@example.test',
                'guest_phone' => '08030000000',
                'selected_room_ids' => [$room->id],
                'check_in' => now()->addDay()->toDateString(),
                'check_out' => now()->addDays(2)->toDateString(),
                'adults' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['guest_name' => 'Next Guest']);
    }

    public function test_updating_dates_to_overlap_another_booking_is_rejected(): void
    {
        $property = Property::create(['name' => 'Moorelife Resort', 'location' => 'Lagos', 'amenities' => []]);
        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive',
            'max_occupancy' => 2,
            'base_price' => 80000,
            'features' => [],
        ]);
        $roomA = Room::create(['property_id' => $property->id, 'room_type_id' => $roomType->id, 'name' => 'A', 'status' => 'available', 'meta' => []]);
        $roomB = Room::create(['property_id' => $property->id, 'room_type_id' => $roomType->id, 'name' => 'B', 'status' => 'available', 'meta' => []]);

        $this->bookingFixture($roomA, now()->addDay(), now()->addDays(3));

        $bookingB = Booking::create([
            'property_id' => $property->id,
            'room_id' => $roomB->id,
            'booking_code' => 'FD-B-1',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'guest_name' => 'Later Guest',
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'nightly_rate' => 80000,
            'total_amount' => 160000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
        ]);
        $bookingB->rooms()->attach($roomB->id, ['status' => 'reserved']);

        $this->actingAs($this->frontdeskUser())
            ->putJson(route('frontdesk.bookings.update', $bookingB), [
                'guest_name' => 'Later Guest',
                'selected_room_ids' => [$roomA->id],
                'check_in' => now()->addDay()->toDateString(),
                'check_out' => now()->addDays(3)->toDateString(),
                'adults' => 1,
                'status' => 'confirmed',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('selected_room_ids');
    }

    public function test_front_desk_is_refused_a_booking_beyond_the_cutoff(): void
    {
        config(['sync.node_type' => 'lan_server']);

        $this->actingAs($this->frontdeskUser())
            ->postJson('/frontdesk/bookings', [
                'guest_name' => 'Three Weeks Out',
                'guest_email' => 'future@example.test',
                'selected_room_ids' => [$this->roomFixture()->id],
                'check_in' => now()->addDays(21)->toDateString(),
                'check_out' => now()->addDays(23)->toDateString(),
                'adults' => 1,
            ])
            ->assertStatus(422);
    }

    public function test_extending_a_stay_past_the_cutoff_still_works(): void
    {
        config(['sync.node_type' => 'lan_server']);

        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now()->addDay(), now()->addDays(3));

        // Arrival unchanged (tomorrow, inside the window); only the checkout moves
        // three weeks out. The guard must not fire.
        $this->actingAs($this->frontdeskUser())
            ->put(route('frontdesk.bookings.update', $booking), [
                'guest_name' => $booking->guest_name,
                'selected_room_ids' => [$room->id],
                'check_in' => now()->addDay()->toDateString(),
                'check_out' => now()->addDays(21)->toDateString(),
                'adults' => 1,
                'status' => 'confirmed',
            ])
            ->assertRedirect();

        $this->assertSame(
            now()->addDays(21)->toDateString(),
            $booking->fresh()->check_out?->toDateString(),
        );
    }

    public function test_the_pivot_checkout_marks_the_room_dirty(): void
    {
        $room = $this->roomFixture();
        $booking = $this->bookingFixture($room, now(), now()->addDay());
        $room->update(['status' => 'occupied']);

        $booking->rooms()->first()->pivot->checkOut();

        $this->assertSame('dirty', $room->fresh()->status);
    }

    protected function roomFixture(): Room
    {
        $property = Property::create([
            'name' => 'Moorelife Resort',
            'location' => 'Lagos',
            'amenities' => [],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive',
            'max_occupancy' => 2,
            'base_price' => 80000,
            'features' => [],
        ]);

        return Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Executive 201',
            'status' => 'available',
            'meta' => [],
        ]);
    }

    protected function bookingFixture(Room $room, $checkIn, $checkOut): Booking
    {
        return tap($this->makeBooking($room, $checkIn, $checkOut), function (Booking $booking) use ($room) {
            $booking->rooms()->attach($room->id, ['status' => 'reserved']);
        });
    }

    protected function makeBooking(Room $room, $checkIn, $checkOut): Booking
    {
        $booking = Booking::create([
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'booking_code' => 'FD-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'check_in' => \Carbon\Carbon::parse($checkIn)->toDateString(),
            'check_out' => \Carbon\Carbon::parse($checkOut)->toDateString(),
            'guest_name' => 'Existing Guest',
            'room_type_id' => $room->room_type_id,
            'quantity' => 1,
            'nightly_rate' => 80000,
            'total_amount' => 160000,
            'status' => 'confirmed',
            'payment_status' => 'pending',
        ]);

        return $booking;
    }

    protected function frontdeskUser(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'frontdesk', 'guard_name' => 'web'],
            ['slug' => 'frontdesk'],
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
