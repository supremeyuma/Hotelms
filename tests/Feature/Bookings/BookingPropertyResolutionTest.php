<?php

namespace Tests\Feature\Bookings;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A booking must belong to the property its room belongs to. BookingService used
 * to fall back to the literal id `1` when no property_id was supplied, which
 * only ever worked because a single-property deployment happens to have that id.
 * On Postgres the ids climb across a run while the foreign key is enforced, so
 * the booking violated `bookings_property_id_foreign` — this is what the Phase 8
 * Postgres reconnaissance surfaced (see task-4-report.md, F12).
 */
class BookingPropertyResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_booking_inherits_the_property_of_its_room(): void
    {
        // Put a property in front of the real one so the room's property is not
        // id 1: a hardcoded default of 1 must not be able to pass by accident.
        Property::create(['name' => 'Filler', 'location' => 'Nowhere', 'amenities' => []]);

        $property = Property::create(['name' => 'Moorelife Resort', 'location' => 'Lagos', 'amenities' => []]);
        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Executive',
            'max_occupancy' => 2,
            'base_price' => 80000,
            'features' => [],
        ]);
        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Executive 201',
            'status' => 'available',
            'meta' => [],
        ]);

        $booking = app(BookingService::class)->createBooking([
            'selected_room_ids' => [$room->id],
            'room_type_id' => $roomType->id,
            'quantity' => 1,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'guest_name' => 'Property Probe',
            'guest_email' => 'probe@example.test',
            'guest_phone' => '08030000000',
        ]);

        $this->assertSame(
            $property->id,
            $booking->property_id,
            'The booking must inherit the property of its room, not fall back to a hardcoded id.',
        );
    }
}
