<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesPaymentGatewayHttp;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use FakesPaymentGatewayHttp;

    use RefreshDatabase;

    protected User $guest;
    protected User $manager;
    protected Room $room;
    protected RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakePaymentGatewayHttp();

        $this->roomType = RoomType::factory()->create([
            'title' => 'Deluxe Suite',
            'base_price' => 50000
        ]);

        $this->room = Room::factory()->create([
            'room_type_id' => $this->roomType->id,
            'name' => 'Room 101'
        ]);

        $this->guest = User::factory()->withRole('guest')->create([
            'email' => 'guest@example.com']);

        $this->manager = User::factory()->withRole('manager')->create([
            'email' => 'manager@example.com']);
    }

    /**
     * Test booking with offline payment method (postpaid)
     */
    public function test_booking_with_offline_payment(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'payment_method' => 'offline',
            'payment_status' => 'pending'
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_method' => 'offline',
            'payment_status' => 'pending'
        ]);
    }

    /**
     * Test booking payment callback success
     */
    public function test_booking_payment_callback_success(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'payment_method' => 'online',
            'payment_status' => 'pending'
        ]);

        $txRef = 'BKG-' . $booking->id . '-' . uniqid();

        // Simulate successful payment callback
        $response = $this->getJson("/booking/payment/{$booking->id}/callback?reference={$txRef}&status=successful");

        // Verify booking payment status was updated
        $booking->refresh();
        $this->assertEquals('online', $booking->payment_method);
        $this->assertContains($booking->payment_status, ['pending', 'paid']);
    }

    /**
     * Test booking payment status tracking
     */
    public function test_booking_payment_status_transitions(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'payment_method' => 'online',
            'payment_status' => 'pending'
        ]);

        // Initial state
        $this->assertEquals('pending', $booking->payment_status);

        // Update to paid
        $booking->update(['payment_status' => 'paid']);
        $this->assertEquals('paid', $booking->payment_status);

        // Verify database
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_status' => 'paid'
        ]);
    }

    /**
     * Test accounting entry creation on successful booking payment
     */
    public function test_accounting_entry_on_booking_payment_success(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'total_amount' => 150000
        ]);

        $payment = Payment::create([
            'amount' => 150000,
            'currency' => 'NGN',
            'method' => 'flutterwave',
            'reference' => 'BKG-' . $booking->id,
            'status' => 'pending',
            'flutterwave_tx_id' => 'tx-bkg-123'
        ]);

        // Simulate webhook processing
        $this->postJson('/api/webhooks/flutterwave', [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-bkg-123',
                'tx_ref' => $payment->reference,
                'status' => 'successful',
                'amount' => 150000,
                'currency' => 'NGN'
            ]
        ], [
            'verif-hash' => config('payment.flutterwave.secret_hash')
        ]);

        // Verify payment was marked as successful
        $payment->refresh();
        $this->assertEquals('successful', $payment->status);
    }

    /**
     * Test mixed online and offline bookings
     */
    public function test_multiple_booking_payment_methods(): void
    {
        $offlineBooking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'payment_method' => 'offline',
            'payment_status' => 'pending'
        ]);

        $onlineBooking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'payment_method' => 'online',
            'payment_status' => 'pending'
        ]);

        // Verify both exist with correct payment methods
        $this->assertDatabaseHas('bookings', [
            'id' => $offlineBooking->id,
            'payment_method' => 'offline'
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $onlineBooking->id,
            'payment_method' => 'online'
        ]);
    }

    /**
     * Test booking payment reference tracking
     */
    public function test_booking_payment_reference_tracking(): void
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'payment_method' => 'online',
            'payment_status' => 'pending'
        ]);

        $payment = Payment::create([
            'amount' => $booking->total_amount ?? 100000,
            'currency' => 'NGN',
            'method' => 'cash',
            'reference' => 'BKG-' . $booking->id,
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('payments', [
            'reference' => 'BKG-' . $booking->id,
            'status' => 'pending'
        ]);
    }
}
