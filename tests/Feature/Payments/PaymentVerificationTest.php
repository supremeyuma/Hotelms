<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Payment;
use App\Models\RoomAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $guest;
    protected Room $room;
    protected RoomType $roomType;
    protected Booking $booking;
    protected RoomAccessToken $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roomType = RoomType::factory()->create([
            'name' => 'Deluxe Suite',
            'nightly_rate' => 50000
        ]);

        $this->room = Room::factory()->create([
            'room_type_id' => $this->roomType->id,
            'room_number' => '101'
        ]);

        $this->guest = User::factory()->create([
            'email' => 'guest@example.com',
            'role' => 'guest'
        ]);

        $this->booking = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'room_id' => $this->room->id,
            'guest_email' => 'guest@example.com',
            'guest_name' => 'John Doe',
            'payment_status' => 'pending'
        ]);

        $this->accessToken = RoomAccessToken::factory()->create([
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id
        ]);
    }

    /**
     * Test payment verification endpoint exists and is accessible
     */
    public function test_payment_verification_endpoint_accessible(): void
    {
        $response = $this->postJson('/payments/verify', [
            'reference' => 'TEST-REFERENCE-123',
            'provider' => 'flutterwave'
        ]);

        // Should either succeed or fail gracefully, not 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    /**
     * Test payment initialization creates pending payment record
     */
    public function test_payment_initialization_creates_pending_record(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $reference = $response->json('reference');

        $this->assertDatabaseHas('payments', [
            'reference' => $reference,
            'status' => 'pending',
            'amount' => 50000,
            'provider' => 'flutterwave',
            'user_id' => $this->guest->id
        ]);
    }

    /**
     * Test multiple payments for same booking are tracked separately
     */
    public function test_multiple_payments_tracked_separately(): void
    {
        $response1 = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response2 = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 25000,
                'provider' => 'paystack'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $ref1 = $response1->json('reference');
        $ref2 = $response2->json('reference');

        $this->assertNotEquals($ref1, $ref2);

        $this->assertDatabaseHas('payments', [
            'reference' => $ref1,
            'amount' => 50000,
            'provider' => 'flutterwave'
        ]);

        $this->assertDatabaseHas('payments', [
            'reference' => $ref2,
            'amount' => 25000,
            'provider' => 'paystack'
        ]);
    }

    /**
     * Test payment initialization with different amounts
     */
    public function test_payment_initialization_with_various_amounts(): void
    {
        $amounts = [1000, 50000, 100000, 500000];

        foreach ($amounts as $amount) {
            $response = $this->postJson(
                '/guest/room/' . $this->accessToken->token . '/payment',
                [
                    'amount' => $amount,
                    'provider' => 'flutterwave'
                ],
                [
                    'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
                ]
            );

            $response->assertStatus(200);
            $response->assertJsonPath('amount', $amount);

            $this->assertDatabaseHas('payments', [
                'amount' => $amount,
                'status' => 'pending'
            ]);
        }
    }

    /**
     * Test payment reference format follows expected pattern
     */
    public function test_payment_reference_format(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $reference = $response->json('reference');

        // Reference should start with BILL- and contain booking ID
        $this->assertStringStartsWith('BILL-', $reference);
        $this->assertStringContainsString((string)$this->booking->id, $reference);
    }

    /**
     * Test payment record contains user association
     */
    public function test_payment_record_associated_with_user(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $reference = $response->json('reference');
        $payment = Payment::where('reference', $reference)->first();

        $this->assertEquals($this->guest->id, $payment->user_id);
        $this->assertEquals('room_bill', $payment->payment_type);
    }

    /**
     * Test payment data consistency across initialization
     */
    public function test_payment_data_consistency(): void
    {
        $testAmount = 50000;
        $testProvider = 'flutterwave';

        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => $testAmount,
                'provider' => $testProvider
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $responseData = $response->json();
        $reference = $responseData['reference'];

        $payment = Payment::where('reference', $reference)->first();

        // Verify consistency
        $this->assertEquals($testAmount, $payment->amount);
        $this->assertEquals($testAmount, $responseData['amount']);
        $this->assertEquals($testProvider, $payment->provider);
        $this->assertEquals($testProvider, $responseData['provider']);
        $this->assertEquals('NGN', $payment->currency);
        $this->assertEquals('NGN', $responseData['currency']);
    }

    /**
     * Test payment initialization error handling
     */
    public function test_payment_initialization_error_handling(): void
    {
        // Test with negative amount
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => -1000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(422);

        // Test with empty request
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(422);
    }

    /**
     * Test payment initialization success response structure
     */
    public function test_payment_success_response_structure(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertJsonStructure([
            'success',
            'reference',
            'tx_ref',
            'provider',
            'amount',
            'currency',
            'description',
            'customer' => [
                'email',
                'name'
            ]
        ]);

        $response->assertJsonPath('success', true);
        $response->assertJsonPath('currency', 'NGN');
    }

    /**
     * Test end-to-end payment flow: initialization
     */
    public function test_end_to_end_payment_initialization(): void
    {
        // Step 1: Initialize payment
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $reference = $response->json('reference');

        // Step 2: Verify payment record exists
        $this->assertDatabaseHas('payments', [
            'reference' => $reference,
            'status' => 'pending',
            'amount' => 50000,
            'currency' => 'NGN'
        ]);

        // Step 3: Verify payment can be retrieved
        $payment = Payment::where('reference', $reference)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals($this->guest->id, $payment->user_id);
    }
}
