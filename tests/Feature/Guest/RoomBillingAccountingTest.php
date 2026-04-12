<?php

namespace Tests\Feature\Guest;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAccessToken;
use App\Models\RoomType;
use App\Services\PaymentProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomBillingAccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_bill_history_uses_payments_table_and_current_outstanding(): void
    {
        [$room, $booking, $accessToken] = $this->createGuestRoomContext();

        Charge::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'description' => 'Laundry charge',
            'amount' => 12000,
            'status' => 'unpaid',
            'payment_mode' => 'postpaid',
            'type' => 'laundry',
            'charge_date' => now()->toDateString(),
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'amount' => 5000,
            'amount_paid' => 5000,
            'currency' => 'NGN',
            'method' => 'card',
            'reference' => 'ROOM-PAY-1',
            'transaction_ref' => 'ROOM-PAY-1',
            'status' => 'completed',
            'provider' => 'manual',
            'payment_type' => 'room_bill',
            'paid_at' => now(),
        ]);

        $response = $this->get(route('guest.room.bill-history', ['token' => $accessToken->token]));

        $response->assertOk();
        $response->assertJsonPath('outstanding', 7000);
        $response->assertJsonCount(2, 'history');
        $response->assertJsonFragment([
            'type' => 'payment',
            'description' => 'Payment',
            'amount' => 5000.0,
        ]);
    }

    public function test_guest_bill_payment_callback_updates_existing_payment_without_creating_duplicate(): void
    {
        [$room, $booking, $accessToken] = $this->createGuestRoomContext();

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'amount' => 15000,
            'currency' => 'NGN',
            'method' => 'flutterwave',
            'reference' => 'BILL-REF-1',
            'payment_reference' => 'BILL-REF-1',
            'transaction_ref' => 'BILL-REF-1',
            'status' => 'pending',
            'provider' => 'flutterwave',
            'payment_type' => 'room_bill',
        ]);

        $this->mock(PaymentProviderManager::class, function ($mock) {
            $mock->shouldReceive('verifyPayment')
                ->once()
                ->with('BILL-REF-1')
                ->andReturn(['success' => true]);
        });

        $response = $this->get(route('guest.bill.payment.callback', [
            'room' => $room->id,
            'reference' => $payment->reference,
        ]));

        $response->assertRedirect(route('guest.room.dashboard', ['token' => $accessToken->token]));

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'reference' => 'BILL-REF-1',
            'status' => 'completed',
        ]);
    }

    protected function createGuestRoomContext(): array
    {
        $property = Property::create([
            'name' => 'Moorelife Resort',
            'location' => 'Lagos',
            'amenities' => ['wifi'],
        ]);

        $roomType = RoomType::create([
            'property_id' => $property->id,
            'title' => 'Suite',
            'max_occupancy' => 2,
            'base_price' => 45000,
            'features' => ['wifi'],
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
            'name' => 'Ocean 12',
            'display_name' => 'Ocean 12',
            'code' => 'O12',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'property_id' => $property->id,
            'room_id' => $room->id,
            'booking_code' => 'GST-1001',
            'check_in' => now()->subDay()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'guests' => 2,
            'total_amount' => 45000,
            'status' => 'checked_in',
            'payment_method' => 'card',
            'payment_status' => 'partial',
            'nightly_rate' => 45000,
            'guest_name' => 'Guest Resident',
            'guest_email' => 'guestresident@example.com',
        ]);

        $booking->rooms()->attach($room->id, [
            'status' => 'checked_in',
            'rate_override' => 45000,
            'checked_in_at' => now()->subHours(6),
            'checked_out_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accessToken = RoomAccessToken::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'token' => 'guest-room-token-' . $booking->id,
            'expires_at' => now()->addDay(),
        ]);

        return [$room, $booking, $accessToken];
    }
}
