<?php

namespace Tests\Feature\Payments;

use App\Models\EventTicketType;
use App\Models\Event;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTicketPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Event $event;
    protected EventTicketType $ticketType;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'guest@example.com',
            'role' => 'guest'
        ]);

        $this->event = Event::factory()->create([
            'name' => 'Test Event',
            'event_date' => now()->addDays(10),
            'is_active' => true
        ]);

        $this->ticketType = EventTicketType::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Regular Ticket',
            'price' => 5000,
            'quantity' => 100
        ]);
    }

    /**
     * Test event ticket payment initialization
     */
    public function test_event_ticket_payment_initialization(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/payments/initialize', [
                'amount' => 5000,
                'currency' => 'NGN',
                'description' => 'Event Ticket Purchase',
                'customer_email' => $this->user->email,
                'customer_name' => $this->user->name,
                'tx_ref' => 'evt-' . uniqid()
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'public_key',
            'payment_link'
        ]);

        // Verify Payment record was created
        $this->assertDatabaseHas('payments', [
            'amount' => 5000,
            'currency' => 'NGN',
            'status' => 'pending'
        ]);
    }

    /**
     * Test event ticket payment callback
     */
    public function test_event_ticket_payment_callback_success(): void
    {
        // Create a pending payment
        $payment = Payment::create([
            'amount' => 5000,
            'currency' => 'NGN',
            'reference' => 'FLW-' . uniqid(),
            'status' => 'pending',
            'flutterwave_tx_id' => 'tx-123456'
        ]);

        $response = $this->postJson('/api/webhooks/flutterwave', [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-123456',
                'tx_ref' => $payment->reference,
                'status' => 'successful',
                'amount' => 5000,
                'currency' => 'NGN'
            ]
        ], [
            'verif-hash' => hash_hmac('sha512', json_encode([
                'event' => 'charge.completed',
                'data' => [
                    'id' => 'tx-123456',
                    'tx_ref' => $payment->reference,
                    'status' => 'successful',
                    'amount' => 5000,
                    'currency' => 'NGN'
                ]
            ]), config('payment.flutterwave.secret_hash'))
        ]);

        // Verify payment was updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'successful',
            'flutterwave_tx_id' => 'tx-123456'
        ]);
    }

    /**
     * Test payment reference tracking
     */
    public function test_payment_reference_is_tracked(): void
    {
        $txRef = 'evt-' . uniqid();
        
        $response = $this->actingAs($this->user)
            ->postJson('/payments/initialize', [
                'amount' => 5000,
                'currency' => 'NGN',
                'description' => 'Event Ticket Purchase',
                'customer_email' => $this->user->email,
                'customer_name' => $this->user->name,
                'tx_ref' => $txRef
            ]);

        $this->assertDatabaseHas('payments', [
            'reference' => $txRef
        ]);
    }

    /**
     * Test multiple payment attempts for same ticket
     */
    public function test_multiple_payment_attempts(): void
    {
        $txRef1 = 'evt-' . uniqid();
        $txRef2 = 'evt-' . uniqid();

        // First payment attempt
        $response1 = $this->actingAs($this->user)
            ->postJson('/payments/initialize', [
                'amount' => 5000,
                'currency' => 'NGN',
                'description' => 'Event Ticket Purchase',
                'customer_email' => $this->user->email,
                'customer_name' => $this->user->name,
                'tx_ref' => $txRef1
            ]);

        // Second payment attempt
        $response2 = $this->actingAs($this->user)
            ->postJson('/payments/initialize', [
                'amount' => 5000,
                'currency' => 'NGN',
                'description' => 'Event Ticket Purchase',
                'customer_email' => $this->user->email,
                'customer_name' => $this->user->name,
                'tx_ref' => $txRef2
            ]);

        // Both should create separate payment records
        $this->assertDatabaseHas('payments', ['reference' => $txRef1]);
        $this->assertDatabaseHas('payments', ['reference' => $txRef2]);
    }

    /**
     * Test payment failure handling
     */
    public function test_payment_failure_handling(): void
    {
        $payment = Payment::create([
            'amount' => 5000,
            'currency' => 'NGN',
            'reference' => 'FLW-' . uniqid(),
            'status' => 'pending',
            'flutterwave_tx_id' => 'tx-failed-123'
        ]);

        $response = $this->postJson('/api/webhooks/flutterwave', [
            'event' => 'charge.failed',
            'data' => [
                'id' => 'tx-failed-123',
                'tx_ref' => $payment->reference,
                'status' => 'failed',
                'amount' => 5000,
                'currency' => 'NGN'
            ]
        ], [
            'verif-hash' => hash_hmac('sha512', json_encode([
                'event' => 'charge.failed',
                'data' => [
                    'id' => 'tx-failed-123',
                    'tx_ref' => $payment->reference,
                    'status' => 'failed',
                    'amount' => 5000,
                    'currency' => 'NGN'
                ]
            ]), config('payment.flutterwave.secret_hash'))
        ]);

        // Verify payment was marked as failed
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed'
        ]);
    }
}
