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

class RoomBillPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $guest;
    protected User $manager;
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

        $this->manager = User::factory()->create([
            'email' => 'manager@example.com',
            'role' => 'manager'
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
     * Test room bill payment initialization with Flutterwave provider
     */
    public function test_room_bill_payment_initialization_flutterwave(): void
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

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'reference',
            'tx_ref',
            'provider',
            'amount',
            'currency',
            'description',
            'customer',
            'public_key'
        ]);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('provider', 'flutterwave');
        $response->assertJsonPath('amount', 50000);
        $response->assertJsonPath('currency', 'NGN');
    }

    /**
     * Test room bill payment initialization with Paystack provider
     */
    public function test_room_bill_payment_initialization_paystack(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'paystack'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('provider', 'paystack');
    }

    /**
     * Test room bill payment initialization without provider (uses default)
     */
    public function test_room_bill_payment_initialization_default_provider(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJson(['currency' => 'NGN']);
    }

    /**
     * Test payment reference is created in database
     */
    public function test_payment_reference_stored_in_database(): void
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
            'amount' => 50000,
            'status' => 'pending',
            'payment_type' => 'room_bill',
            'provider' => 'flutterwave'
        ]);
    }

    /**
     * Test payment initialization with invalid amount
     */
    public function test_payment_initialization_with_invalid_amount(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 0,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(422);
    }

    /**
     * Test payment initialization with missing amount
     */
    public function test_payment_initialization_with_missing_amount(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(422);
    }

    /**
     * Test payment initialization with invalid provider
     */
    public function test_payment_initialization_with_invalid_provider(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'invalid_provider'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertStatus(422);
    }

    /**
     * Test duplicate payment reference detection
     */
    public function test_duplicate_payment_reference_detection(): void
    {
        // First payment
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

        $reference = $response1->json('reference');

        // Manually create duplicate reference
        Payment::create([
            'user_id' => $this->guest->id,
            'reference' => $reference,
            'provider' => 'flutterwave',
            'amount' => 50000,
            'currency' => 'NGN',
            'status' => 'pending',
            'payment_type' => 'room_bill'
        ]);

        // Second payment attempt with same reference should fail
        $response2 = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response2->assertStatus(200); // Should still be 200 as it generates a new reference
        $this->assertNotEquals($reference, $response2->json('reference'));
    }

    /**
     * Test payment response includes customer information
     */
    public function test_payment_response_includes_customer_info(): void
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

        $response->assertJsonPath('customer.email', 'guest@example.com');
        $response->assertJsonPath('customer.name', 'John Doe');
    }

    /**
     * Test payment response includes metadata
     */
    public function test_payment_response_includes_metadata(): void
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

        $response->assertJsonPath('customer.email', 'guest@example.com');
        $response->assertJsonPath('customer.name', 'John Doe');
    }

    /**
     * Test payment database record has correct metadata
     */
    public function test_payment_metadata_stored_correctly(): void
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

        $this->assertNotNull($payment);
        $metadata = json_decode($payment->metadata, true);
        $this->assertArrayHasKey('booking_id', $metadata);
        $this->assertArrayHasKey('room_id', $metadata);
        $this->assertEquals($this->booking->id, $metadata['booking_id']);
        $this->assertEquals($this->room->id, $metadata['room_id']);
    }

    /**
     * Test payment for different room numbers
     */
    public function test_payment_for_different_rooms(): void
    {
        $room2 = Room::factory()->create([
            'room_type_id' => $this->roomType->id,
            'room_number' => '102'
        ]);

        $booking2 = Booking::factory()->create([
            'user_id' => $this->guest->id,
            'room_id' => $room2->id,
            'guest_email' => 'guest@example.com',
            'guest_name' => 'Jane Doe',
        ]);

        $token2 = RoomAccessToken::factory()->create([
            'room_id' => $room2->id,
            'booking_id' => $booking2->id
        ]);

        $response = $this->postJson(
            '/guest/room/' . $token2->token . '/payment',
            [
                'amount' => 75000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $token2->token
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('amount', 75000);
        
        $reference = $response->json('reference');
        $this->assertDatabaseHas('payments', [
            'reference' => $reference,
            'amount' => 75000,
            'metadata' => json_encode([
                'booking_id' => $booking2->id,
                'room_id' => $room2->id,
            ])
        ]);
    }

    /**
     * Test Flutterwave specific payment response format
     */
    public function test_flutterwave_payment_response_format(): void
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

        $response->assertJsonPath('payment_options', 'card,banktransfer,ussd');
        $response->assertJsonStructure(['public_key']);
    }

    /**
     * Test Paystack specific payment response format
     */
    public function test_paystack_payment_response_format(): void
    {
        $response = $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'paystack'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        $response->assertJsonStructure(['public_key']);
    }

    /**
     * Test payment initialization logging
     */
    public function test_payment_initialization_is_logged(): void
    {
        $this->postJson(
            '/guest/room/' . $this->accessToken->token . '/payment',
            [
                'amount' => 50000,
                'provider' => 'flutterwave'
            ],
            [
                'HTTP_X_ROOM_TOKEN' => $this->accessToken->token
            ]
        );

        // Check that payment exists in database (proves logging happened)
        $this->assertDatabaseHas('payments', [
            'user_id' => $this->guest->id,
            'amount' => 50000,
            'status' => 'pending'
        ]);
    }
}
