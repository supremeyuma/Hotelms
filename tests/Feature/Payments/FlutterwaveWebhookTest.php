<?php

namespace Tests\Feature\Payments;

use Tests\TestCase;

class FlutterwaveWebhookTest extends TestCase
{
    public function test_webhook_rejects_missing_signature_header(): void
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

        config()->set('payment.flutterwave.secret_hash', 'test-secret-hash');

        $this->postJson('/api/webhooks/flutterwave', $payload)
            ->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

    public function test_webhook_rejects_invalid_signature(): void
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

        config()->set('payment.flutterwave.secret_hash', 'test-secret-hash');

        $this->postJson('/api/webhooks/flutterwave', $payload, [
            'verif-hash' => 'invalid-hash-signature',
        ])->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

}
