<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * PaystackService: Production-ready Paystack payment processing
 * 
 * Handles payment initialization, verification, and webhooks
 * with comprehensive error handling and logging.
 */
class PaystackService
{
    protected string $baseUrl;
    protected string $secretKey;
    protected string $publicKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('payment.paystack.base_url', 'https://api.paystack.co');
        $this->secretKey = config('payment.paystack.secret_key');
        $this->publicKey = config('payment.paystack.public_key');
        $this->timeout = config('payment.paystack.timeout', 30);

        if (!$this->secretKey) {
            throw new Exception('Paystack secret key is not configured');
        }

        if (!$this->publicKey) {
            throw new Exception('Paystack public key is not configured');
        }
    }

    /**
     * Initialize a payment transaction
     * 
     * @param array $paymentData {
     *     'email' => string (required),
     *     'amount' => float (in kobo, e.g., 50000 for ₦500),
     *     'currency' => string (default: NGN),
     *     'metadata' => array (optional),
     *     'tx_ref' => string (optional, unique reference),
     *     'callback_url' => string (optional),
     * }
     * @return array
     */
    public function initializePayment(array $paymentData): array
    {
        try {
            // Validate required fields
            if (empty($paymentData['email'])) {
                throw new Exception('Customer email is required');
            }

            if (empty($paymentData['amount']) || $paymentData['amount'] <= 0) {
                throw new Exception('Valid payment amount is required');
            }

            // Convert amount to kobo if in naira
            $amountInKobo = $this->convertToKobo($paymentData['amount']);

            // Build request payload
            $payload = [
                'email' => $paymentData['email'],
                'amount' => $amountInKobo,
                'currency' => $paymentData['currency'] ?? 'NGN',
                'reference' => $paymentData['reference'] ?? $paymentData['tx_ref'] ?? $this->generateReference(),
            ];

            // Add metadata if provided
            if (!empty($paymentData['metadata'])) {
                $payload['metadata'] = $paymentData['metadata'];
            }

            // Add callback URL if provided
            if (!empty($paymentData['callback_url'])) {
                $payload['callback_url'] = $paymentData['callback_url'];
            }

            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/transaction/initialize", $payload);

            if (!$response->successful()) {
                $error = $response->json('message') ?? 'Payment initialization failed';
                Log::error('Paystack payment initialization failed', [
                    'status' => $response->status(),
                    'error' => $error,
                    'payload' => $paymentData,
                ]);

                return [
                    'success' => false,
                    'error' => $error,
                    'status_code' => $response->status(),
                ];
            }

            $data = $response->json('data');

            return [
                'success' => true,
                'reference' => $data['reference'] ?? null,
                'authorization_url' => $data['authorization_url'] ?? null,
                'access_code' => $data['access_code'] ?? null,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'NGN',
                'metadata' => $data['metadata'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('Paystack payment initialization exception: ' . $e->getMessage(), [
                'payload' => $paymentData,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => 'Payment initialization failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify a payment transaction
     * 
     * @param string $reference Transaction reference from Paystack
     * @return array
     */
    public function verifyPayment(string $reference): array
    {
        try {
            if (empty($reference)) {
                throw new Exception('Payment reference is required');
            }

            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/transaction/verify/{$reference}");

            if (!$response->successful()) {
                $error = $response->json('message') ?? 'Payment verification failed';
                Log::warning('Paystack payment verification failed', [
                    'reference' => $reference,
                    'error' => $error,
                ]);

                return [
                    'success' => false,
                    'verified' => false,
                    'error' => $error,
                ];
            }

            $data = $response->json('data');
            $status = $data['status'] ?? 'failed';
            $verified = $status === 'success';

            return [
                'success' => true,
                'verified' => $verified,
                'reference' => $reference,
                'status' => $status,
                'amount' => $data['amount'] ?? 0,
                'currency' => $data['currency'] ?? 'NGN',
                'customer' => $data['customer'] ?? null,
                'authorization' => $data['authorization'] ?? null,
                'paid_at' => $data['paid_at'] ?? null,
                'created_at' => $data['created_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::error('Paystack payment verification exception: ' . $e->getMessage(), [
                'reference' => $reference,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'verified' => false,
                'error' => 'Verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get available payment methods
     * 
     * @return array
     */
    public function getAvailablePaymentMethods(): array
    {
        return [
            [
                'value' => 'paystack',
                'label' => 'Paystack',
                'description' => 'Secure payment with card, bank transfer, or USSD',
                'icon' => 'paystack',
            ],
        ];
    }

    /**
     * Validate webhook signature
     * 
     * @param string $signature The signature from the request header
     * @param string $payload The raw request body
     * @return bool
     */
    public function validateWebhookSignature(string $signature, string $payload): bool
    {
        try {
            $webhookSecret = config('payment.paystack.webhook_secret') ?: $this->secretKey;

            if (!$webhookSecret) {
                Log::warning('Paystack webhook secret not configured');
                return false;
            }

            $hash = hash_hmac('sha512', $payload, $webhookSecret);

            return hash_equals($hash, $signature);
        } catch (Exception $e) {
            Log::error('Paystack webhook signature validation exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process webhook event
     * 
     * @param array $data Webhook payload data
     * @return array
     */
    public function processWebhookEvent(array $data): array
    {
        try {
            $event = $data['event'] ?? null;
            $reference = $data['data']['reference'] ?? null;

            if (!$event || !$reference) {
                throw new Exception('Invalid webhook payload');
            }

            Log::info("Processing Paystack webhook event: {$event}", [
                'reference' => $reference,
                'data' => $data,
            ]);

            return [
                'success' => true,
                'event' => $event,
                'reference' => $reference,
                'data' => $data['data'] ?? [],
            ];
        } catch (Exception $e) {
            Log::error('Paystack webhook processing exception: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'error' => 'Webhook processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create payment plan
     * 
     * @param array $planData
     * @return array
     */
    public function createPaymentPlan(array $planData): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/plan", $planData);

            if (!$response->successful()) {
                $error = $response->json('message') ?? 'Plan creation failed';
                Log::error('Paystack plan creation failed', [
                    'error' => $error,
                    'payload' => $planData,
                ]);

                return [
                    'success' => false,
                    'error' => $error,
                ];
            }

            return [
                'success' => true,
                'data' => $response->json('data') ?? [],
            ];
        } catch (Exception $e) {
            Log::error('Paystack plan creation exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Plan creation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get customer transactions
     * 
     * @param string $customerEmail
     * @return array
     */
    public function getCustomerTransactions(string $customerEmail): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/customer/{$customerEmail}");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to fetch customer data',
                ];
            }

            return [
                'success' => true,
                'data' => $response->json('data') ?? [],
            ];
        } catch (Exception $e) {
            Log::error('Paystack customer fetch exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Customer fetch failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convert naira to kobo
     * Paystack requires amounts in kobo (1 naira = 100 kobo)
     * 
     * @param float $amount Amount in naira
     * @return int Amount in kobo
     */
    protected function convertToKobo(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Generate unique transaction reference
     * 
     * @return string
     */
    protected function generateReference(): string
    {
        return 'PAY-' . strtoupper(\Illuminate\Support\Str::random(12)) . '-' . time();
    }

    /**
     * Get public key for frontend
     * 
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
