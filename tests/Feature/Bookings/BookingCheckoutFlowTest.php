<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use App\Services\RoomBalanceService;
use App\Events\RoomBillingUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class BookingCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_checkout_uses_billing_service_and_completes(): void
    {
        Event::fake([RoomBillingUpdated::class]);

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
            'booking_code' => 'BKG-CHECKOUT-1001',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'nightly_rate' => 50000,
            'total_amount' => 100000,
            'status' => 'checked_in',
            'payment_status' => 'paid',
            'guest_name' => 'Jane Guest',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '08000000000',
            'details' => [],
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'active',
            'checked_in_at' => now()->subDay(),
        ]);

        $roomBalanceMock = Mockery::mock(RoomBalanceService::class);
        $roomBalanceMock->shouldReceive('roomCanCheckout')
            ->once()
            ->withArgs(fn (Booking $checkedOutBooking, Room $checkedOutRoom) => $checkedOutBooking->is($booking) && $checkedOutRoom->is($room))
            ->andReturnTrue();
        $this->app->instance(RoomBalanceService::class, $roomBalanceMock);

        app(BookingService::class)->checkOut($booking, $room);

        $booking->refresh();
        $room->refresh();

        $this->assertSame('checked_in', $booking->status);
        $this->assertSame('dirty', $room->status);
        $this->assertDatabaseHas('charges', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'type' => 'nightly',
        ]);
        $this->assertDatabaseHas('booking_rooms', [
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'status' => 'checked_out',
        ]);
    }
}
