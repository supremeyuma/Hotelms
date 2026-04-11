<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use App\Services\EventService;
use Carbon\Carbon;

class FlutterwaveService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->secretKey = (string) config('payment.flutterwave.secret_key', '');
        $this->publicKey = (string) config('payment.flutterwave.public_key', '');
        $this->baseUrl = (string) config('payment.flutterwave.base_url', 'https://api.flutterwave.com/v3');
        $this->timeout = (int) config('payment.flutterwave.timeout', 30);
    }

    /**
     * Initialize a payment transaction with Flutterwave
     * 
     * @param array $paymentData {
     *     'email' => string (customer email),
     *     'name' => string (customer name),
     *     'amount' => float (amount in NGN),
     *     'currency' => string (default: NGN),
     *     'reference' => string (unique transaction reference),
     *     'metadata' => array (optional metadata),
     *     'description' => string (transaction description),
     * }
     * @return array Payment initialization response
     */
    public function initializePayment(array $paymentData): array
    {
        try {
            if (empty($paymentData['email'])) {
                throw new \InvalidArgumentException('Customer email is required');
            }

            if (empty($paymentData['amount']) || (float) $paymentData['amount'] <= 0) {
                throw new \InvalidArgumentException('Valid payment amount is required');
            }

            $reference = $paymentData['reference'] ?? $paymentData['tx_ref'] ?? ('FLW-' . strtoupper(bin2hex(random_bytes(6))));
            $customerName = $paymentData['name'] ?? trim(($paymentData['first_name'] ?? '') . ' ' . ($paymentData['last_name'] ?? ''));

            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/payments", [
                    'tx_ref' => $reference,
                    'amount' => (float) $paymentData['amount'],
                    'currency' => $paymentData['currency'] ?? 'NGN',
                    'redirect_url' => $paymentData['callback_url'] ?? null,
                    'customer' => [
                        'email' => $paymentData['email'],
                        'name' => $customerName ?: 'Guest Customer',
                        'phonenumber' => $paymentData['phone_number'] ?? $paymentData['phone'] ?? null,
                    ],
                    'meta' => $paymentData['metadata'] ?? [],
                    'customizations' => [
                        'title' => 'Hotel Payment',
                        'description' => $paymentData['description'] ?? 'Payment',
                    ],
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('data') ?? [],
                    'reference' => $reference,
                    'payment_link' => $response->json('data.link'),
                    'amount' => (float) ($paymentData['amount'] ?? 0),
                    'currency' => $paymentData['currency'] ?? 'NGN',
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message') ?? 'Payment initialization failed',
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave payment initialization failed', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData,
            ]);
            
            return [
                'success' => false,
                'error' => 'Payment initialization failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify a payment transaction with Flutterwave
     * 
     * @param string $reference Unique transaction reference
     * @return array Payment verification response with verified status
     */
    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/transactions/verify_by_reference/" . urlencode($reference));

            if ($response->successful()) {
                $data = $response->json('data');
                $status = $data['status'] ?? null;
                $amount = $data['amount'] ?? 0;

                if ($status === 'successful' && $amount > 0) {
                    return [
                        'success' => true,
                        'verified' => true,
                        'data' => $data,
                        'reference' => $reference,
                        'amount' => $amount,
                        'currency' => $data['currency'] ?? 'NGN',
                    ];
                }
            }

            return [
                'success' => false,
                'verified' => false,
                'error' => $response->json('message') ?? 'Payment verification failed',
                'data' => $response->json('data') ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave payment verification failed', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);
            
            return [
                'success' => false,
                'verified' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getPaymentPoints(?string $email = null): array
    {
        try {
            // Mock payment points logic - in real app, you would query a customer payment_points table
            // For this demo, we'll create some sample data
            
            $paymentPoints = DB::table('payment_points')
                ->where('customer_email', $email)
                ->sum('points');
            
            // If no email provided, return general points
            if (!$email) {
                $points = DB::table('payment_points')->sum('points');
            }
            
            // Mock customer loyalty tiers
            $tier = 'bronze';
            $spent = DB::table('payment_points')->where('customer_email', $email)->sum('amount_spent');
            
            return [
                'current_points' => $points ?? 0,
                'tier' => $tier,
                'tier_name' => ucfirst($tier),
                'tier_description' => $this->getTierDescription($tier),
                'points_needed_for_next_tier' => $this->getPointsForNextTier($points, $tier),
                'points_spent' => $spent,
                'total_spent' => $spent,
            ];
        } catch (\Exception $e) {
            Log::error('Payment points lookup failed: ' . $e->getMessage());
            
            return [
                'current_points' => 0,
                'tier' => 'bronze',
                'tier_description' => $this->getTierDescription('bronze'),
                'points_needed_for_next_tier' => 100,
                'points_spent' => 0,
                'total_spent' => 0,
            ];
        }
    }

    public function applyPaymentPoints(Payment $payment): array
    {
        try {
            // Mock payment points logic
            $points = $payment->amount * 0.01; // 1 point per ₦10 spent
            
            $customerEmail = $payment->booking ? $payment->booking->guest_email : null;
            
            if (!$customerEmail) {
                Log::warning('Payment points applied without customer email');
                return [
                    'success' => false,
                    'error' => 'Customer email required for payment points',
                ];
            }
            
            // Add points to customer account
            DB::table('payment_points')->insert([
                'customer_email' => $customerEmail,
                'payment_id' => $payment->id,
                'points' => $points,
                'points_spent' => $payment->amount,
                'created_at' => now(),
                'description' => "Payment for booking {$payment->booking_id}",
            ]);
            
            return [
                'success' => true,
                'points_applied' => $points,
                'message' => 'Payment points applied successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Payment points application failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available payment methods for Flutterwave
     * 
     * @return array List of available payment methods
     */
    public function getAvailablePaymentMethods(): array
    {
        return [
            [
                'value' => 'flutterwave',
                'label' => 'Flutterwave Pay',
                'description' => 'Secure online payment with card, bank transfer, USSD, or mobile money',
                'icon' => 'credit-card',
                'fees' => [
                    'visa' => 'Visa Card',
                    'mastercard' => 'Mastercard',
                    'verve' => 'Verve',
                    'amex' => 'American Express',
                    'discover' => 'Discover',
                    'diners_club' => 'Diners Club',
                    'jcb' => 'JCB',
                    'unionpay' => 'UnionPay',
                ],
            ],
        ];
    }

    /**
     * Get transaction status from Flutterwave
     * 
     * @param string $reference Transaction reference
     * @return array Transaction status data
     */
    public function getFlutterwaveTransactionStatus(string $reference): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/transactions/" . urlencode($reference));

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->json('data.status') ?? 'pending',
                    'data' => $response->json('data'),
                ];
            }

            return [
                'success' => false,
                'status' => 'failed',
                'error' => $response->json('message') ?? 'Status check failed',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave status check failed', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);
            
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Process a refund for a payment
     * 
     * @param Payment $payment Payment model instance
     * @param string $reason Reason for refund
     * @return array Refund result
     */
    public function refundPayment(Payment $payment, string $reason): array
    {
        try {
            $response = Http::withToken($this->secretKey)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/refunds", [
                    'transaction_id' => $payment->flutterwave_tx_id,
                    'reason' => $reason,
                ]);

            if ($response->successful()) {
                $payment->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'flutterwave_refund_id' => $response->json('data.id'),
                ]);
                
                return [
                    'success' => true,
                    'refunded' => true,
                    'message' => 'Payment refunded successfully',
                ];
            }

            return [
                'success' => false,
                'refunded' => false,
                'error' => $response->json('message') ?? 'Refund failed',
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave refund failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);
            
            return [
                'success' => false,
                'refunded' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get public key for frontend
     * 
     * @return string|null Public key or null if not configured
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey ?: null;
    }

    protected function getTierDescription(string $tier): string
    {
        return match ($tier) {
            'bronze' => 'Bronze Tier (0-2,499 points needed for Silver)',
            'silver' => 'Silver Tier (500-9,999 points needed for Gold)',
            'gold' => 'Gold Tier (1,000+ points needed for Platinum)',
            'platinum' => 'Platinum Tier (2,500+ points needed for Diamond)',
            default => 'No Tier',
        };
    }

    protected function getPointsForNextTier(int $currentPoints, string $tier): int
    {
        $tiers = [
            'bronze' => 500, 'silver' => 1000, 'gold' => 2500, 'platinum' => 5000,
        ];
        
        $currentTierIndex = array_search($tier, array_keys($tiers));
        
        if ($currentTierIndex === false) {
            return 0;
        }
        
        $nextTierIndex = $currentTierIndex + 1;
        
        $tierKeys = array_keys($tiers);
        $currentTierKey = $tierKeys[$currentTierIndex] ?? null;
        $nextTierKey = $tierKeys[$nextTierIndex] ?? null;
        
        if (!$currentTierKey || !$nextTierKey) {
            return 0;
        }
        
        return $tiers[$nextTierKey] - $tiers[$currentTierKey];
    }

    /**
     * Verify webhook signature from Flutterwave
     * 
     * @param string $payload Webhook payload
     * @param string $signature Signature to verify
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        try {
            $secretHash = config('payment.flutterwave.secret_hash');
            
            if (!$secretHash) {
                Log::warning('Flutterwave secret hash not configured');
                return false;
            }
            
            return hash_equals($secretHash, $signature);
        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Process incoming webhook from Flutterwave
     * 
     * @param array $webhookData Webhook payload data
     * @return array Processing result
     */
    public function processWebhook(array $webhookData): array
    {
        try {
            $eventType = $webhookData['event'] ?? '';
            
            Log::info('Processing Flutterwave webhook', ['event_type' => $eventType]);
            
            return match ($eventType) {
                'charge.completed' => $this->handleSuccessfulPayment($webhookData),
                'charge.failed' => $this->handleFailedPayment($webhookData),
                'transfer.completed' => $this->handleTransferCompleted($webhookData),
                'transfer.failed' => $this->handleTransferFailed($webhookData),
                'refund.completed' => $this->handleRefundCompleted($webhookData),
                'refund.failed' => $this->handleRefundFailed($webhookData),
                default => [
                    'success' => false,
                    'message' => 'Unhandled event type: ' . $eventType,
                ],
            };
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData,
            ]);
            
            return [
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle successful payment webhook event
     * 
     * @param array $data Webhook event data
     * @return array Processing result
     */
    protected function handleSuccessfulPayment(array $data): array
    {
        $paymentData = $data['data'] ?? [];
        $reference = $paymentData['tx_ref'] ?? $paymentData['reference'] ?? null;
        $amount = $paymentData['amount'] ?? 0;
        $currency = $paymentData['currency'] ?? 'NGN';
        
        if (!$reference) {
            return ['success' => false, 'message' => 'No payment reference found'];
        }
        
        // Update payment status in database
        $payment = Payment::where('flutterwave_tx_ref', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();
            
        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'flutterwave_tx_id' => $paymentData['id'] ?? null,
                'flutterwave_tx_status' => 'successful',
                'amount_paid' => $amount,
                'currency' => $currency,
                'raw_response' => json_encode($paymentData),
                'paid_at' => now(),
            ]);
            
            // Apply payment points
            $this->applyPaymentPoints($payment);

            // Post to accounting ledger
            try {
                resolve(\App\Services\PaymentAccountingService::class)->handleSuccessful($payment);
            } catch (\Exception $e) {
                Log::error('Payment accounting handler failed', [
                    'error' => $e->getMessage(),
                    'payment_id' => $payment->id,
                ]);
            }

            // Confirm event ticket if this is an event payment
            if ($payment->description && str_contains($payment->description, 'event')) {
                resolve(EventService::class)->confirmPayment($reference, 'flutterwave', 'paid');
            }
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'payment_id' => $payment->id,
            ];
        }
        
        // If no payment found, this might be for event tickets
        try {
            resolve(EventService::class)->confirmPayment($reference, 'flutterwave', 'paid');
            return ['success' => true, 'message' => 'Event payment confirmed'];
        } catch (\Exception $e) {
            Log::warning('Could not find payment or event ticket for reference', [
                'reference' => $reference,
            ]);
            return ['success' => false, 'message' => 'Payment not found'];
        }
    }

    /**
     * Handle failed payment webhook event
     * 
     * @param array $data Webhook event data
     * @return array Processing result
     */
    protected function handleFailedPayment(array $data): array
    {
        $paymentData = $data['data'] ?? [];
        $reference = $paymentData['tx_ref'] ?? $paymentData['reference'] ?? null;
        
        if (!$reference) {
            return ['success' => false, 'message' => 'No payment reference found'];
        }
        
        $payment = Payment::where('flutterwave_tx_ref', $reference)
            ->orWhere('payment_reference', $reference)
            ->first();
            
        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'flutterwave_tx_status' => 'failed',
                'raw_response' => json_encode($paymentData),
            ]);
        }
        
        // Update event ticket status if applicable
        try {
            resolve(EventService::class)->confirmPayment($reference, 'flutterwave', 'failed');
        } catch (\Exception $e) {
            // Ignore if no event ticket found
        }
        
        return ['success' => true, 'message' => 'Payment failure processed'];
    }

    /**
     * Handle transfer completed webhook event
     * 
     * @param array $data Webhook event data
     * @return array Processing result
     */
    protected function handleTransferCompleted(array $data): array
    {
        Log::info('Transfer completed', ['data' => $data]);
        return ['success' => true, 'message' => 'Transfer completed'];
    }

    /**
     * Handle transfer failed webhook event
     * 
     * @param array $data Webhook event data
     * @return array Processing result
     */
    protected function handleTransferFailed(array $data): array
    {
        Log::error('Transfer failed', ['data' => $data]);
        return ['success' => true, 'message' => 'Transfer failure processed'];
    }

    /**
     * Handle refund completed webhook event
     * 
     * @param array $data Webhook event data
     * @return array Processing result
     */
    protected function handleRefundCompleted(array $data): array
    {
        $refundData = $data['data'] ?? [];
        $transactionId = $refundData['transaction_id'] ?? null;
        
        if ($transactionId) {
            Payment::where('flutterwave_tx_id', $transactionId)
                ->orWhere('flutterwave_refund_id', $transactionId)
                ->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'flutterwave_refund_id' => $refundData['id'] ?? null,
                ]);
        }
        
        return ['success' => true, 'message' => 'Refund completed'];
    }

    /**
     * Handle refund failed webhook event
     * 
     * @param array $data Webhook event data
     * @return array Processing result
     */
    protected function handleRefundFailed(array $data): array
    {
        Log::error('Refund failed', ['data' => $data]);
        return ['success' => true, 'message' => 'Refund failure processed'];
    }}
