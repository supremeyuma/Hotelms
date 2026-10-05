<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Fakes the Flutterwave and Paystack HTTP APIs.
 *
 * The payment feature tests exercise real controller and service code, so the
 * gateway endpoints must be stubbed for the suite to stay hermetic and
 * deterministic. Without these fakes the tests attempt live network calls.
 */
trait FakesPaymentGatewayHttp
{
    protected function fakePaymentGatewayHttp(): void
    {
        config([
            'payment.flutterwave.public_key' => 'FLWPUBK-TEST',
            'payment.flutterwave.secret_key' => 'FLWSECRET-TEST',
        'payment.flutterwave.secret_hash' => 'flw-webhook-secret',
            'payment.paystack.public_key' => 'pk_test',
            'payment.paystack.secret_key' => 'sk_test',
        ]);

        Http::fake([
            // Flutterwave: create payment link
            'api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'message' => 'Payment Link Created',
                'data' => [
                    'id' => 1234567,
                    'tx_ref' => 'FLW-TEST-REF',
                    'link' => 'https://checkout.flutterwave.com/pay/flw-test',
                    'public_key' => 'FLWPUBK-TEST',
                ],
            ], 200),

            // Flutterwave: verify a transaction
            'api.flutterwave.com/v3/transactions/*/verify' => Http::response([
                'status' => 'success',
                'message' => 'Transaction details retrieved',
                'data' => [
                    'id' => 1234567,
                    'status' => 'successful',
                    'tx_ref' => 'FLW-TEST-REF',
                    'amount' => 50000,
                    'currency' => 'NGN',
                    'customer' => [
                        'email' => 'guest@example.com',
                        'name' => 'John Doe',
                    ],
                ],
            ], 200),

            // Paystack: initialise a transaction
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/ps-test',
                    'access_code' => 'PS_TEST_ACCESS',
                    'reference' => 'PS-TEST-REF',
                ],
            ], 200),

            // Paystack: verify a transaction
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'reference' => 'PS-TEST-REF',
                    'amount' => 50000,
                    'currency' => 'NGN',
                    'customer' => [
                        'email' => 'guest@example.com',
                        'name' => 'John Doe',
                    ],
                ],
            ], 200),

            // Anything else a gateway call may reach for.
            '*' => Http::response([
                'status' => 'success',
                'message' => 'OK',
                'data' => [],
            ], 200),
        ]);
    }
}