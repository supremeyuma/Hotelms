<?php

namespace Tests\Feature\Payments;

use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    public function test_paystack_webhook_requires_signature_header(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'paystack-ref-001',
                'status' => 'success',
                'amount' => 100000,
                'currency' => 'NGN',
            ],
        ];

        $this->postJson('/api/webhooks/paystack', $payload)
            ->assertStatus(400)
            ->assertJson(['error' => 'Missing signature']);
    }

    public function test_paystack_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'paystack-ref-001',
                'status' => 'success',
                'amount' => 100000,
                'currency' => 'NGN',
            ],
        ];

        config()->set('payment.paystack.webhook_secret', 'test-paystack-secret');

        $this->postJson('/api/webhooks/paystack', $payload, [
            'x-paystack-signature' => 'invalid-signature',
        ])->assertStatus(401)
            ->assertJson(['error' => 'Invalid signature']);
    }

}
