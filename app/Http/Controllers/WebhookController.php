<?php

namespace App\Http\Controllers;

use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\EventService;
use App\Services\PaymentAccountingService;
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
        protected BookingService $bookingService,
        protected EventService $eventService,
        protected PaymentAccountingService $paymentAccountingService,
    ) {}

    /**
     * Handle Flutterwave webhooks
     */
    public function handleFlutterwaveWebhook(Request $request)
    {
        try {
            $signature = $request->header('verif-hash');
            $payload = $request->getContent();

            if (!is_string($signature) || $signature === '') {
                Log::warning('Missing Flutterwave webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

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
        if (!$request->hasHeader('x-paystack-signature')) {
            return response()->json(['error' => 'Missing signature'], 400);
        }

        $reference = $request->input('data.reference');
        if (!$reference) {
            return response()->json(['error' => 'Missing reference'], 400);
        }

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

            $idempotencyKey = $this->buildIdempotencyKey('flutterwave', $data);
            if ($idempotencyKey && $this->isIdempotencyKeyProcessed($idempotencyKey)) {
                return response()->json(['status' => 'already_processed']);
            }

            // Try event ticket
            if ($ticket = EventTicket::where('qr_code', $reference)->first()) {
                if ($ticket->payment_status === 'paid') {
                    return response()->json(['status' => 'already_processed']);
                }

                $this->eventService->confirmPayment($reference, 'flutterwave', 'paid');
                $this->recordWebhookIdempotency($idempotencyKey, [
                    'booking_id' => null,
                    'reference' => $reference,
                    'provider' => 'flutterwave',
                    'payment_type' => 'event_ticket',
                    'raw_response' => $data,
                ]);

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
                $this->recordWebhookIdempotency($idempotencyKey, [
                    'booking_id' => null,
                    'reference' => $reference,
                    'provider' => 'flutterwave',
                    'payment_type' => 'event_table',
                    'raw_response' => $data,
                ]);

                Log::info('Flutterwave table reservation payment confirmed', [
                    'reference' => $reference,
                    'reservation_id' => $reservation->id,
                ]);

                return response()->json(['status' => 'processed']);
            }

            // Try room booking
            if ($booking = Booking::where('booking_code', $reference)->first()) {
                return $this->handleBookingPaymentSuccess($booking, 'flutterwave', $data);
            }

            if ($payment = $this->findPaymentByReference($reference)) {
                return $this->handleStandalonePaymentSuccess($payment, 'flutterwave', $data);
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

            if ($booking = Booking::where('booking_code', $reference)->first()) {
                $booking->update([
                    'payment_status' => 'failed',
                    'payment_method' => 'flutterwave',
                ]);
            }

            if ($payment = $this->findPaymentByReference($reference)) {
                $this->markPaymentFailed($payment, 'flutterwave', $data);
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

            $idempotencyKey = $this->buildIdempotencyKey('paystack', $data);
            if ($idempotencyKey && $this->isIdempotencyKeyProcessed($idempotencyKey)) {
                return response()->json(['status' => 'already_processed']);
            }

            // Try event ticket
            if ($ticket = EventTicket::where('qr_code', $reference)->first()) {
                if ($ticket->payment_status === 'paid') {
                    return response()->json(['status' => 'already_processed']);
                }

                $this->eventService->confirmPayment($reference, 'paystack', 'paid');
                $this->recordWebhookIdempotency($idempotencyKey, [
                    'booking_id' => null,
                    'reference' => $reference,
                    'provider' => 'paystack',
                    'payment_type' => 'event_ticket',
                    'raw_response' => $data,
                ]);

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
                $this->recordWebhookIdempotency($idempotencyKey, [
                    'booking_id' => null,
                    'reference' => $reference,
                    'provider' => 'paystack',
                    'payment_type' => 'event_table',
                    'raw_response' => $data,
                ]);

                Log::info('Paystack table reservation payment confirmed', [
                    'reference' => $reference,
                    'reservation_id' => $reservation->id,
                ]);

                return response()->json(['status' => 'processed']);
            }

            // Try room booking
            if ($booking = Booking::where('booking_code', $reference)->first()) {
                return $this->handleBookingPaymentSuccess($booking, 'paystack', $data);
            }

            if ($payment = $this->findPaymentByReference($reference)) {
                return $this->handleStandalonePaymentSuccess($payment, 'paystack', $data);
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

            if ($booking = Booking::where('booking_code', $reference)->first()) {
                $booking->update([
                    'payment_status' => 'failed',
                    'payment_method' => 'paystack',
                ]);
            }

            if ($payment = $this->findPaymentByReference($reference)) {
                $this->markPaymentFailed($payment, 'paystack', $data);
            }

            return response()->json(['status' => 'processed']);

        } catch (\Exception $e) {
            Log::error('Paystack payment failure handling failed: ' . $e->getMessage());
            return response()->json(['error' => 'Processing error'], 500);
        }
    }

    /**
     * Handle successful booking payment (room bookings)
     */
    private function handleBookingPaymentSuccess(Booking $booking, string $provider, array $data): \Illuminate\Http\JsonResponse
    {
        if ($booking->payment_status === 'paid') {
            return response()->json(['status' => 'already_processed']);
        }

        $payloadData = $data['data'] ?? [];
        $amount = $payloadData['amount'] ?? $booking->total_amount;
        $currency = $payloadData['currency'] ?? 'NGN';
        $idempotencyKey = $this->buildIdempotencyKey($provider, $data);

        if ($idempotencyKey && $this->isIdempotencyKeyProcessed($idempotencyKey)) {
            return response()->json(['status' => 'already_processed']);
        }

        $booking->update([
            'payment_status' => 'paid',
            'payment_method' => $provider,
        ]);

        $payment = Payment::updateOrCreate(
            [
                'booking_id' => $booking->id,
                'reference' => $booking->booking_code,
            ],
            [
                'method' => $provider,
                'provider' => $provider,
                'amount' => $booking->total_amount,
                'amount_paid' => $amount,
                'currency' => $currency,
                'status' => 'completed',
                'payment_type' => 'booking',
                'transaction_ref' => $booking->booking_code,
                'verified_at' => now(),
                'external_reference' => $payloadData['id'] ?? null,
                'flutterwave_tx_ref' => $provider === 'flutterwave' ? ($payloadData['tx_ref'] ?? $booking->booking_code) : null,
                'flutterwave_tx_id' => $provider === 'flutterwave' ? ($payloadData['id'] ?? null) : null,
                'flutterwave_tx_status' => $provider === 'flutterwave' ? ($payloadData['status'] ?? null) : null,
                'idempotency_key' => $idempotencyKey,
                'meta' => $payloadData ?: $data,
                'raw_response' => $payloadData ?: $data,
                'paid_at' => now(),
            ]
        );

        try {
            $this->paymentAccountingService->handleSuccessful($payment);
        } catch (\Exception $e) {
            Log::error('Payment accounting handler failed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
            ]);
        }

        $this->bookingService->confirmBooking($booking);

        Log::info('Booking payment confirmed via webhook', [
            'booking_id' => $booking->id,
            'reference' => $booking->booking_code,
            'provider' => $provider,
        ]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * Store idempotency key for non-booking webhook events
     */
    private function recordWebhookIdempotency(?string $idempotencyKey, array $data): void
    {
        if (!$idempotencyKey) {
            return;
        }

        if ($this->isIdempotencyKeyProcessed($idempotencyKey)) {
            return;
        }

        Payment::create([
            'booking_id' => $data['booking_id'] ?? null,
            'method' => $data['provider'] ?? null,
            'reference' => $data['reference'] ?? null,
            'transaction_ref' => $data['reference'] ?? null,
            'provider' => $data['provider'] ?? null,
            'status' => 'completed',
            'payment_type' => $data['payment_type'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'meta' => $data['raw_response'] ?? null,
            'raw_response' => $data['raw_response'] ?? null,
            'paid_at' => now(),
            'verified_at' => now(),
        ]);
    }

    /**
     * Build a stable idempotency key for webhook payloads
     */
    private function buildIdempotencyKey(string $provider, array $data): ?string
    {
        $payload = $data['data'] ?? [];
        $event = $data['event'] ?? 'unknown';
        $reference = $payload['tx_ref'] ?? $payload['reference'] ?? null;
        $transactionId = $payload['id'] ?? $payload['transaction_id'] ?? null;

        if (!$reference && !$transactionId) {
            return null;
        }

        $raw = implode('|', [
            $provider,
            $event,
            $reference ?? 'no-ref',
            $transactionId ?? 'no-tx',
        ]);

        return hash('sha256', $raw);
    }

    /**
     * Check if an idempotency key has already been processed
     */
    private function isIdempotencyKeyProcessed(string $idempotencyKey): bool
    {
        return Payment::where('idempotency_key', $idempotencyKey)->exists();
    }

    private function handleStandalonePaymentSuccess(Payment $payment, string $provider, array $data): \Illuminate\Http\JsonResponse
    {
        $payloadData = $data['data'] ?? [];
        $idempotencyKey = $this->buildIdempotencyKey($provider, $data);

        if ($idempotencyKey && $this->isIdempotencyKeyProcessed($idempotencyKey) && $payment->status === 'completed') {
            return response()->json(['status' => 'already_processed']);
        }

        $payment->update([
            'method' => $provider,
            'provider' => $provider,
            'amount_paid' => $payloadData['amount'] ?? $payment->amount_paid ?? $payment->amount,
            'currency' => $payloadData['currency'] ?? $payment->currency ?? 'NGN',
            'status' => 'completed',
            'transaction_ref' => $payloadData['tx_ref'] ?? $payloadData['reference'] ?? $payment->transaction_ref,
            'verified_at' => now(),
            'external_reference' => $payloadData['id'] ?? $payment->external_reference,
            'flutterwave_tx_ref' => $provider === 'flutterwave' ? ($payloadData['tx_ref'] ?? $payment->flutterwave_tx_ref ?? $payment->reference) : $payment->flutterwave_tx_ref,
            'flutterwave_tx_id' => $provider === 'flutterwave' ? ($payloadData['id'] ?? $payment->flutterwave_tx_id) : $payment->flutterwave_tx_id,
            'flutterwave_tx_status' => $provider === 'flutterwave' ? ($payloadData['status'] ?? $payment->flutterwave_tx_status) : $payment->flutterwave_tx_status,
            'idempotency_key' => $idempotencyKey ?? $payment->idempotency_key,
            'meta' => $payloadData ?: $data,
            'raw_response' => $payloadData ?: $data,
            'paid_at' => now(),
        ]);

        $this->paymentAccountingService->handleSuccessful($payment);

        return response()->json(['status' => 'processed']);
    }

    private function markPaymentFailed(Payment $payment, string $provider, array $data): void
    {
        $payloadData = $data['data'] ?? [];

        $payment->update([
            'method' => $provider,
            'provider' => $provider,
            'status' => 'failed',
            'transaction_ref' => $payloadData['tx_ref'] ?? $payloadData['reference'] ?? $payment->transaction_ref,
            'meta' => $payloadData ?: $data,
            'flutterwave_tx_status' => $provider === 'flutterwave' ? ($payloadData['status'] ?? 'failed') : $payment->flutterwave_tx_status,
            'raw_response' => $payloadData ?: $data,
        ]);
    }

    private function findPaymentByReference(?string $reference): ?Payment
    {
        if (! $reference) {
            return null;
        }

        return Payment::query()
            ->where('reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->orWhere('flutterwave_tx_ref', $reference)
            ->orWhere('external_reference', $reference)
            ->first();
    }

    /**
     * Validate Flutterwave webhook signature
     */
    private function validateFlutterwaveSignature(?string $signature, string $payload): bool
    {
        try {
            if (!is_string($signature) || $signature === '') {
                return false;
            }

            $secretHash = config('payment.flutterwave.secret_hash');
            if (!$secretHash) {
                Log::warning('Flutterwave secret hash not configured');
                return false;
            }

            return hash_equals($secretHash, $signature);
        } catch (\Exception $e) {
            Log::error('Flutterwave signature validation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate Paystack webhook signature
     */
    private function validatePaystackSignature(?string $signature, string $payload): bool
    {
        try {
            if (!is_string($signature) || $signature === '') {
                return false;
            }

            $secret = config('payment.paystack.webhook_secret') ?: config('payment.paystack.secret_key');
            if (!$secret) {
                Log::warning('Paystack secret key not configured');
                return false;
            }

            $hash = hash_hmac('sha512', $payload, $secret);
            return hash_equals($hash, $signature);
        } catch (\Exception $e) {
            Log::error('Paystack signature validation failed: ' . $e->getMessage());
            return false;
        }
    }
}
