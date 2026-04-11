<?php

namespace Tests\Feature\Bookings;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingConfirmationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_booking_dispatches_guest_confirmation_email_once(): void
    {
        Mail::fake();

        $booking = $this->createBooking();

        app(BookingService::class)->confirmBooking($booking);

        Mail::assertSent(BookingConfirmationMail::class, function (BookingConfirmationMail $mail) use ($booking) {
            return $mail->hasTo($booking->guest_email)
                && $mail->booking->is($booking->fresh());
        });

        $booking->refresh();

        $this->assertSame('confirmed', $booking->status);
        $this->assertNotNull(data_get($booking->details, 'notifications.booking_confirmation_sent_at'));

        Mail::fake();

        app(BookingService::class)->confirmBooking($booking->fresh());

        Mail::assertNothingSent();
    }

    private function createBooking(): Booking
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
            'status' => 'available',
        ]);

        return Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'booking_code' => 'BKG-CONFIRM-1001',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'quantity' => 1,
            'adults' => 2,
            'children' => 0,
            'nightly_rate' => 50000,
            'total_amount' => 50000,
            'status' => 'pending_payment',
            'payment_status' => 'paid',
            'guest_name' => 'Jane Guest',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '08000000000',
            'details' => [],
        ]);
    }
}
