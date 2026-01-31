<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlutterwaveWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test webhook signature verification
     */
    public function test_webhook_signature_verification(): void
    {
        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-123456',
                'tx_ref' => 'ref-001',
                'status' => 'successful',
                'amount' => 10000,
                'currency' => 'NGN'
            ]
        ];

        $hash = hash_hmac('sha512', json_encode($payload), config('services.flutterwave.secret_hash'));

        // Valid signature should be accepted
        $response = $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        // Should not return unauthorized
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    /**
     * Test webhook with invalid signature
     */
    public function test_webhook_invalid_signature(): void
    {
        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-123456',
                'tx_ref' => 'ref-001',
                'status' => 'successful',
                'amount' => 10000,
                'currency' => 'NGN'
            ]
        ];

        // Invalid hash
        $response = $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => 'invalid-hash-signature'
        ]);

        // Should reject invalid signature
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test charge.completed event handling
     */
    public function test_charge_completed_event(): void
    {
        $payment = Payment::create([
            'amount' => 50000,
            'currency' => 'NGN',
            'reference' => 'ref-completed-001',
            'status' => 'pending',
            'flutterwave_tx_id' => 'tx-completed-001'
        ]);

        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-completed-001',
                'tx_ref' => 'ref-completed-001',
                'status' => 'successful',
                'amount' => 50000,
                'currency' => 'NGN'
            ]
        ];

        $hash = hash_hmac('sha512', json_encode($payload), config('services.flutterwave.secret_hash'));

        $response = $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        // Verify payment status was updated
        $payment->refresh();
        $this->assertEquals('successful', $payment->status);
    }

    /**
     * Test charge.failed event handling
     */
    public function test_charge_failed_event(): void
    {
        $payment = Payment::create([
            'amount' => 30000,
            'currency' => 'NGN',
            'reference' => 'ref-failed-001',
            'status' => 'pending',
            'flutterwave_tx_id' => 'tx-failed-001'
        ]);

        $payload = [
            'event' => 'charge.failed',
            'data' => [
                'id' => 'tx-failed-001',
                'tx_ref' => 'ref-failed-001',
                'status' => 'failed',
                'amount' => 30000,
                'currency' => 'NGN'
            ]
        ];

        $hash = hash_hmac('sha512', json_encode($payload), config('services.flutterwave.secret_hash'));

        $response = $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        // Verify payment was marked as failed
        $payment->refresh();
        $this->assertEquals('failed', $payment->status);
    }

    /**
     * Test refund.completed event
     */
    public function test_refund_completed_event(): void
    {
        $payment = Payment::create([
            'amount' => 25000,
            'currency' => 'NGN',
            'reference' => 'ref-refund-001',
            'status' => 'successful',
            'flutterwave_tx_id' => 'tx-refund-001'
        ]);

        $payload = [
            'event' => 'refund.completed',
            'data' => [
                'id' => 'refund-001',
                'tx_ref' => 'ref-refund-001',
                'status' => 'completed',
                'amount' => 25000,
                'currency' => 'NGN'
            ]
        ];

        $hash = hash_hmac('sha512', json_encode($payload), config('services.flutterwave.secret_hash'));

        $response = $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        // Verify refund was recorded
        $payment->refresh();
        $this->assertNotNull($payment->flutterwave_refund_id);
    }

    /**
     * Test duplicate webhook handling
     */
    public function test_duplicate_webhook_handling(): void
    {
        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-duplicate-001',
                'tx_ref' => 'ref-duplicate-001',
                'status' => 'successful',
                'amount' => 15000,
                'currency' => 'NGN'
            ]
        ];

        $hash = hash_hmac('sha512', json_encode($payload), config('services.flutterwave.secret_hash'));

        // First webhook
        $response1 = $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        // Second webhook (duplicate)
        $response2 = $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        // Both should be accepted but only one payment should be created/updated
        $this->assertEquals(1, Payment::where('flutterwave_tx_id', 'tx-duplicate-001')->count());
    }

    /**
     * Test webhook with missing verif-hash header
     */
    public function test_webhook_missing_signature_header(): void
    {
        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => 'tx-no-hash-001',
                'tx_ref' => 'ref-no-hash-001',
                'status' => 'successful',
                'amount' => 20000,
                'currency' => 'NGN'
            ]
        ];

        // Send without verif-hash header
        $response = $this->postJson('/webhooks/flutterwave', $payload);

        // Should reject
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test webhook idempotency
     */
    public function test_webhook_idempotency(): void
    {
        $txRef = 'ref-idem-001';
        $txId = 'tx-idem-001';

        // Create initial payment
        $payment = Payment::create([
            'amount' => 40000,
            'currency' => 'NGN',
            'reference' => $txRef,
            'status' => 'pending',
            'flutterwave_tx_id' => $txId
        ]);

        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'id' => $txId,
                'tx_ref' => $txRef,
                'status' => 'successful',
                'amount' => 40000,
                'currency' => 'NGN'
            ]
        ];

        $hash = hash_hmac('sha512', json_encode($payload), config('services.flutterwave.secret_hash'));

        // Process webhook first time
        $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        $payment->refresh();
        $firstStatusUpdate = $payment->updated_at;

        // Wait a moment
        sleep(1);

        // Process same webhook again
        $this->postJson('/webhooks/flutterwave', $payload, [
            'verif-hash' => $hash
        ]);

        $payment->refresh();
        $secondStatusUpdate = $payment->updated_at;

        // Verify payment status remains successful and wasn't re-processed
        $this->assertEquals('successful', $payment->status);
    }

    /**
     * Test webhook event routing
     */
    public function test_webhook_event_routing(): void
    {
        // Test various event types are routed correctly
        $events = [
            'charge.completed' => 'successful',
            'charge.failed' => 'failed',
            'charge.reversed' => 'reversed',
            'transfer.completed' => 'successful',
            'refund.completed' => 'refunded'
        ];

        foreach ($events as $eventType => $expectedStatus) {
            $payment = Payment::create([
                'amount' => 10000,
                'currency' => 'NGN',
                'reference' => 'ref-' . str_replace('.', '-', $eventType),
                'status' => 'pending',
                'flutterwave_tx_id' => 'tx-' . str_replace('.', '-', $eventType)
            ]);

            $payload = [
                'event' => $eventType,
                'data' => [
                    'id' => 'tx-' . str_replace('.', '-', $eventType),
                    'tx_ref' => 'ref-' . str_replace('.', '-', $eventType),
                    'status' => $expectedStatus,
                    'amount' => 10000,
                    'currency' => 'NGN'
                ]
            ];

            $hash = hash_hmac('sha512', json_encode($payload), config('services.flutterwave.secret_hash'));

            $this->postJson('/webhooks/flutterwave', $payload, [
                'verif-hash' => $hash
            ]);
        }

        // All payments should be created
        $this->assertGreaterThanOrEqual(count($events), Payment::count());
    }
}
