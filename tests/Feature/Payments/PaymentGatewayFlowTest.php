<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BookingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentGatewayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payment.flutterwave.public_key', 'FLWPUBK_TEST');
        config()->set('payment.flutterwave.secret_key', 'FLWSECK_TEST');
        config()->set('payment.flutterwave.secret_hash', 'flw-webhook-secret');
        config()->set('payment.paystack.public_key', 'pk_test_paystack');
        config()->set('payment.paystack.secret_key', 'sk_test_paystack');
        config()->set('payment.paystack.webhook_secret', 'paystack-webhook-secret');
        config()->set('payment.providers.flutterwave', true);
        config()->set('payment.providers.paystack', true);
        config()->set('payment.default', 'flutterwave');
    }

    public function test_booking_initialization_returns_provider_payload_and_tracks_pending_payment(): void
    {
        $booking = $this->createBooking();

        $response = $this->postJson('/payments/initialize-booking', [
            'booking_id' => $booking->id,
            'provider' => 'paystack',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'paystack')
            ->assertJsonPath('reference', $booking->booking_code)
            ->assertJsonPath('public_key', 'pk_test_paystack')
            ->assertJsonPath('callback_url', route('booking.payment.callback', $booking));

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'reference' => $booking->booking_code,
            'payment_reference' => $booking->booking_code,
            'provider' => 'paystack',
            'method' => 'paystack',
            'status' => 'pending',
            'payment_type' => 'booking',
        ]);
    }

    public function test_booking_initialization_without_provider_uses_default_provider(): void
    {
        $booking = $this->createBooking();

        $response = $this->postJson('/payments/initialize-booking', [
            'booking_id' => $booking->id,
            'provider' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'flutterwave')
            ->assertJsonCount(2, 'available_providers');
    }

    public function test_booking_initialization_only_returns_operational_gateway_when_other_provider_is_disabled_and_unconfigured(): void
    {
        $booking = $this->createBooking();

        config()->set('payment.providers.flutterwave', true);
        config()->set('payment.providers.paystack', false);
        config()->set('payment.default', 'paystack');
        config()->set('payment.paystack.public_key', null);
        config()->set('payment.paystack.secret_key', null);

        $response = $this->postJson('/payments/initialize-booking', [
            'booking_id' => $booking->id,
            'provider' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider', 'flutterwave')
            ->assertJsonPath('show_provider_options', false)
            ->assertJsonCount(1, 'available_providers')
            ->assertJsonPath('available_providers.0.value', 'flutterwave')
            ->assertJsonPath('public_key', 'FLWPUBK_TEST');
    }

    public function test_flutterwave_webhook_marks_existing_payment_as_completed(): void
    {
        $payment = Payment::create([
            'method' => 'flutterwave',
            'reference' => 'FLW-REF-1001',
            'transaction_ref' => 'FLW-REF-1001',
            'payment_reference' => 'FLW-REF-1001',
            'provider' => 'flutterwave',
            'amount' => 25000,
            'amount_paid' => 25000,
            'currency' => 'NGN',
            'status' => 'pending',
            'payment_type' => 'general',
        ]);

        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'flw_txn_1001',
                'tx_ref' => 'FLW-REF-1001',
                'status' => 'successful',
                'amount' => 25000,
                'currency' => 'NGN',
            ],
        ];

        $this->postJson('/api/webhooks/flutterwave', $payload, [
            'verif-hash' => 'flw-webhook-secret',
        ])->assertOk()
            ->assertJson(['status' => 'processed']);

        $payment->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertSame('flw_txn_1001', $payment->flutterwave_tx_id);
        $this->assertSame('successful', $payment->flutterwave_tx_status);
        $this->assertNotNull($payment->verified_at);
    }

    public function test_paystack_webhook_marks_existing_payment_as_completed(): void
    {
        $payment = Payment::create([
            'method' => 'paystack',
            'reference' => 'PSK-REF-2001',
            'transaction_ref' => 'PSK-REF-2001',
            'payment_reference' => 'PSK-REF-2001',
            'provider' => 'paystack',
            'amount' => 18000,
            'amount_paid' => 18000,
            'currency' => 'NGN',
            'status' => 'pending',
            'payment_type' => 'general',
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 90101,
                'reference' => 'PSK-REF-2001',
                'status' => 'success',
                'amount' => 1800000,
                'currency' => 'NGN',
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'paystack-webhook-secret');

        $this->postJson('/api/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ])->assertOk()
            ->assertJson(['status' => 'processed']);

        $payment->refresh();

        $this->assertSame('completed', $payment->status);
        $this->assertSame('90101', (string) $payment->external_reference);
        $this->assertNotNull($payment->verified_at);
    }

    public function test_paystack_webhook_completes_booking_even_when_payments_method_column_is_missing(): void
    {
        Mail::fake();

        $booking = $this->createBooking();

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('method');
        });

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'id' => 99101,
                'reference' => $booking->booking_code,
                'status' => 'success',
                'amount' => 5000000,
                'currency' => 'NGN',
            ],
        ];

        $signature = hash_hmac('sha512', json_encode($payload), 'paystack-webhook-secret');

        $this->postJson('/api/webhooks/paystack', $payload, [
            'x-paystack-signature' => $signature,
        ])->assertOk()
            ->assertJson(['status' => 'processed']);

        $booking->refresh();

        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paystack', $booking->payment_method);
        $this->assertDatabaseHas('payments', [
            'reference' => $booking->booking_code,
            'provider' => 'paystack',
            'status' => 'completed',
        ]);
    }

    public function test_paid_pending_booking_is_reconciled_to_confirmed_status(): void
    {
        $booking = $this->createBooking();

        $booking->update([
            'payment_status' => 'paid',
            'status' => 'pending_payment',
        ]);

        app(BookingService::class)->reconcilePaidBookingStates();

        $booking->refresh();

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertNull($booking->expires_at);
    }

    public function test_successful_payment_record_reconciles_booking_to_paid_and_confirmed(): void
    {
        $booking = $this->createBooking();

        Payment::create([
            'booking_id' => $booking->id,
            'method' => 'paystack',
            'reference' => $booking->booking_code,
            'transaction_ref' => $booking->booking_code,
            'payment_reference' => $booking->booking_code,
            'provider' => 'paystack',
            'amount' => 50000,
            'amount_paid' => 50000,
            'currency' => 'NGN',
            'status' => 'completed',
            'payment_type' => 'booking',
            'paid_at' => now(),
        ]);

        app(BookingService::class)->reconcilePaidBookingStates();

        $booking->refresh();

        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('paystack', $booking->payment_method);
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
            'booking_code' => 'BKG-TEST-1001',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'guests' => 2,
            'total_amount' => 50000,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'guest_name' => 'Jane Guest',
            'guest_email' => 'jane@example.com',
            'guest_phone' => '08000000000',
        ]);
    }
}
