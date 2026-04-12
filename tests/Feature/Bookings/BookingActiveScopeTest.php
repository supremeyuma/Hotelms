<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingActiveScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_can_be_used_inside_room_bookings_relation_queries(): void
    {
        $property = Property::create([
            'name' => 'Test Hotel',
            'location' => 'Lagos',
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Deluxe',
            'max_occupancy' => 2,
            'base_price' => 50000,
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Room 101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'booking_code' => 'BKG-ACTIVE-1001',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'nightly_rate' => 50000,
            'total_amount' => 50000,
            'status' => 'checked_in',
            'payment_status' => 'paid',
            'guest_name' => 'Jane Guest',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '08000000000',
            'details' => [],
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $matchingRoomIds = Room::query()
            ->whereHas('bookings', fn ($query) => $query->active())
            ->orderBy('name')
            ->pluck('id')
            ->all();

        $this->assertSame([$room->id], $matchingRoomIds);
    }
}
