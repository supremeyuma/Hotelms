<?php

namespace App\Http\Controllers;

use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Services\PaymentProviderManager;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WebhookController: Production-ready webhook handling
 * 
 * Processes webhooks from Flutterwave and Paystack
 * with signature verification and idempotency
 */
class WebhookController extends Controller
{
    public function __construct(
        protected PaymentProviderManager $paymentManager,
        protected EventService $eventService
    ) {}

    /**
     * Handle Flutterwave webhooks
     */
    public function handleFlutterwaveWebhook(Request $request)
    {
        try {
            $signature = $request->header('verif-hash');
            $payload = $request->getContent();

            if (!$this->validateFlutterwaveSignature($signature, $payload)) {
                Log::warning('Invalid Flutterwave webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $data = $request->json()->all();
            $event = $data['event'] ?? null;
            $reference = $data['data']['tx_ref'] ?? null;

            Log::info("Flutterwave webhook received: {$event}", [
                'reference' => $reference,
                'data' => $data,
            ]);

            if ($event === 'charge.completed') {
                return $this->handleFlutterwavePaymentSuccess($data);
            }

            if ($event === 'charge.failed') {
                return $this->handleFlutterwavePaymentFailure($data);
            }

            return response()->json(['status' => 'received']);

        } catch (\Exception $e) {
            Log::error('Flutterwave webhook processing error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle Paystack webhooks
     */
    public function handlePaystackWebhook(Request $request)
    {
        try {
            $signature = $request->header('x-paystack-signature');
            $payload = $request->getContent();

            if (!$this->validatePaystackSignature($signature, $payload)) {
                Log::warning('Invalid Paystack webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $data = $request->json()->all();
            $event = $data['event'] ?? null;
            $reference = $data['data']['reference'] ?? null;

            Log::info("Paystack webhook received: {$event}", [
                'reference' => $reference,
                'data' => $data,
            ]);

            if ($event === 'charge.success') {
                return $this->handlePaystackPaymentSuccess($data);
            }

            if ($event === 'charge.failed' || $event === 'charge.dispute.create') {
                return $this->handlePaystackPaymentFailure($data);
            }

            return response()->json(['status' => 'received']);

        } catch (\Exception $e) {
            Log::error('Paystack webhook processing error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle successful Flutterwave payment
     */
    private function handleFlutterwavePaymentSuccess(array $data): \Illuminate\Http\JsonResponse
    {
        try {
            $reference = $data['data']['tx_ref'] ?? null;
            $status = $data['data']['status'] ?? null;

            if ($status !== 'successful') {
                Log::warning('Flutterwave webhook with non-successful status', [
                    'reference' => $reference,
                    'status' => $status,
                ]);

                return response()->json(['error' => 'Invalid payment status'], 400);
            }

            // Try event ticket
            if ($ticket = EventTicket::where('qr_code', $reference)->first()) {
                if ($ticket->payment_status === 'paid') {
                    return response()->json(['status' => 'already_processed']);
                }

                $this->eventService->confirmPayment($reference, 'flutterwave', 'paid');

                Log::info('Flutterwave event ticket payment confirmed', [
                    'reference' => $reference,
                    'ticket_id' => $ticket->id,
                ]);

                return response()->json(['status' => 'processed']);
            }

            // Try event table reservation
            if ($reservation = EventTableReservation::where('qr_code', $reference)->first()) {
                if ($reservation->payment_status === 'paid') {
                    return response()->json(['status' => 'already_processed']);
                }

                $this->eventService->confirmPayment($reference, 'flutterwave', 'paid');

                Log::info('Flutterwave table reservation payment confirmed', [
                    'reference' => $reference,
                    'reservation_id' => $reservation->id,
                ]);

                return response()->json(['status' => 'processed']);
            }

            return response()->json(['status' => 'no_matching_transaction']);

        } catch (\Exception $e) {
            Log::error('Flutterwave payment success handling failed: ' . $e->getMessage());
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle failed Flutterwave payment
     */
    private function handleFlutterwavePaymentFailure(array $data): \Illuminate\Http\JsonResponse
    {
        try {
            $reference = $data['data']['tx_ref'] ?? null;

            Log::warning('Flutterwave payment failed', [
                'reference' => $reference,
                'data' => $data,
            ]);

            // Mark event ticket or reservation as failed
            if ($ticket = EventTicket::where('qr_code', $reference)->first()) {
                $ticket->update(['payment_status' => 'failed']);
            }

            if ($reservation = EventTableReservation::where('qr_code', $reference)->first()) {
                $reservation->update(['payment_status' => 'failed']);
            }

            return response()->json(['status' => 'processed']);

        } catch (\Exception $e) {
            Log::error('Flutterwave payment failure handling failed: ' . $e->getMessage());
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle successful Paystack payment
     */
    private function handlePaystackPaymentSuccess(array $data): \Illuminate\Http\JsonResponse
    {
        try {
            $reference = $data['data']['reference'] ?? null;
            $status = $data['data']['status'] ?? null;

            if ($status !== 'success') {
                Log::warning('Paystack webhook with non-success status', [
                    'reference' => $reference,
                    'status' => $status,
                ]);

                return response()->json(['error' => 'Invalid payment status'], 400);
            }

            // Try event ticket
            if ($ticket = EventTicket::where('qr_code', $reference)->first()) {
                if ($ticket->payment_status === 'paid') {
                    return response()->json(['status' => 'already_processed']);
                }

                $this->eventService->confirmPayment($reference, 'paystack', 'paid');

                Log::info('Paystack event ticket payment confirmed', [
                    'reference' => $reference,
                    'ticket_id' => $ticket->id,
                ]);

                return response()->json(['status' => 'processed']);
            }

            // Try event table reservation
            if ($reservation = EventTableReservation::where('qr_code', $reference)->first()) {
                if ($reservation->payment_status === 'paid') {
                    return response()->json(['status' => 'already_processed']);
                }

                $this->eventService->confirmPayment($reference, 'paystack', 'paid');

                Log::info('Paystack table reservation payment confirmed', [
                    'reference' => $reference,
                    'reservation_id' => $reservation->id,
                ]);

                return response()->json(['status' => 'processed']);
            }

            return response()->json(['status' => 'no_matching_transaction']);

        } catch (\Exception $e) {
            Log::error('Paystack payment success handling failed: ' . $e->getMessage());
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle failed Paystack payment
     */
    private function handlePaystackPaymentFailure(array $data): \Illuminate\Http\JsonResponse
    {
        try {
            $reference = $data['data']['reference'] ?? null;

            Log::warning('Paystack payment failed', [
                'reference' => $reference,
                'data' => $data,
            ]);

            // Mark event ticket or reservation as failed
            if ($ticket = EventTicket::where('qr_code', $reference)->first()) {
                $ticket->update(['payment_status' => 'failed']);
            }

            if ($reservation = EventTableReservation::where('qr_code', $reference)->first()) {
                $reservation->update(['payment_status' => 'failed']);
            }

            return response()->json(['status' => 'processed']);

        } catch (\Exception $e) {
            Log::error('Paystack payment failure handling failed: ' . $e->getMessage());
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Validate Flutterwave webhook signature
     */
    private function validateFlutterwaveSignature(string $signature, string $payload): bool
    {
        try {
            $hash = hash_hmac('sha256', $payload, config('payment.flutterwave.secret_hash'));
            return hash_equals($hash, $signature);
        } catch (\Exception $e) {
            Log::error('Flutterwave signature validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate Paystack webhook signature
     */
    private function validatePaystackSignature(string $signature, string $payload): bool
    {
        try {
            $hash = hash_hmac('sha512', $payload, config('payment.paystack.webhook_secret'));
            return hash_equals($hash, $signature);
        } catch (\Exception $e) {
            Log::error('Paystack signature validation failed: ' . $e->getMessage());
            return false;
        }
    }
}
