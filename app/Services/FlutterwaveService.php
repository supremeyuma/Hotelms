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
    public function initializePayment(array $paymentData): array
    {
        try {
            $response = Http::withToken(config('flutterwave.secret_key'))
                ->post('https://api.flutterwave.com/v3/charges', $paymentData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'reference' => $response->data('data')['id'] ?? null,
                    'amount' => $response->data('data')['amount'] ?? 0,
                    'currency' => $response->data('data')['currency'] ?? 'NGN',
                ];
            }
        }

        catch (\Exception $e) {
            Log::error('Flutterwave payment initialization failed: ' . $e->getMessage(), [
                'payment_data' => $paymentData,
            ]);
            
            return [
                'success' => false,
                'error' => 'Payment initialization failed',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'success' => false,
            'error' => 'Unknown error occurred',
        ];
    }

    public function verifyPayment(string $reference): array
    {
        try {
            $response = Http::withToken(config('flutterwave.secret_key'))
                ->get("https://api.flutterwave.com/v3/transactions/verify_by_reference/" . $reference);

            if ($response->successful() && $response->json('data')['status'] === 'successful' && $response->json('data')['amount'] > 0) {
                return [
                    'success' => true,
                    'verified' => true,
                    'data' => $response->json('data'),
                    'reference' => $reference,
                    'amount' => $response->json('data')['amount'],
                    'currency' => $response->json('data')['currency'],
                ];
            }

            return [
                'success' => false,
                'verified' => false,
                'error' => $response->json('message') ?? 'Payment verification failed',
                'data' => $response->json('data') ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave payment verification failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'verified' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getPaymentPoints(string $email = null): array
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

    public function getFlutterwaveTransactionStatus(string $reference): array
    {
        try {
            $response = Http::withToken(config('flutterwave.secret_key'))
                ->get("https://api.flutterwave.com/v3/transactions/" . $reference);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->json('data')['status'] ?? 'pending',
                    'data' => $response->json('data'),
                ];
            }
        }

        catch (\Exception $e) {
            Log::error('Flutterwave status check failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }

        return [
            'success' => false,
            'status' => 'failed',
            'error' => 'Unknown error occurred',
            'data' => [],
        ];
    }

    public function refundPayment(Payment $payment, string $reason): array
    {
        try {
            // Mock Flutterwave refund
            $response = Http::withToken(config('flutterwave.secret_key'))
                ->post('https://api.flutterwave.com/v3/refunds', [
                    'transaction_id' => $payment->flutterwave_tx_id,
                    'reason' => $reason,
                ]);

            if ($response->successful()) {
                // Update payment status
                $payment->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'flutterwave_refund_id' => $response->json('data')['id'] ?? null,
                ]);
                
                return [
                    'success' => true,
                    'refunded' => true,
                    'message' => 'Payment refunded successfully',
                ];
            } else {
                return [
                    'success' => false,
                    'refunded' => false,
                    'error' => $response->json('message') ?? 'Refund failed',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Flutterwave refund failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'refunded' => false,
                'error' => $e->getMessage(),
            ];
        }
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
            Log::error('Webhook signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

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
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'webhook_data' => $webhookData,
            ]);
            
            return [
                'success' => false,
                'message' => 'Webhook processing failed',
                'error' => $e->getMessage(),
            ];
        }
    }

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
                Log::error('Payment accounting handler failed: ' . $e->getMessage(), ['payment_id' => $payment->id]);
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
            Log::warning('Could not find payment or event ticket for reference: ' . $reference);
            return ['success' => false, 'message' => 'Payment not found'];
        }
    }

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

    protected function handleTransferCompleted(array $data): array
    {
        Log::info('Transfer completed', ['data' => $data]);
        return ['success' => true, 'message' => 'Transfer completed'];
    }

    protected function handleTransferFailed(array $data): array
    {
        Log::error('Transfer failed', ['data' => $data]);
        return ['success' => true, 'message' => 'Transfer failure processed'];
    }

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

    protected function handleRefundFailed(array $data): array
    {
        Log::error('Refund failed', ['data' => $data]);
        return ['success' => true, 'message' => 'Refund failure processed'];
    }
}
