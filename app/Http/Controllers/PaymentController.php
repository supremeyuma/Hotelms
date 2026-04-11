<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\EventTableReservation;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\Payment;
use App\Services\DiscountCodeService;
use App\Services\EventService;
use App\Services\PaymentAccountingService;
use App\Services\PaymentProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentProviderManager $paymentManager,
        protected EventService $eventService,
        protected DiscountCodeService $discountCodeService,
        protected PaymentAccountingService $paymentAccountingService,
    ) {}

    public function initialize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'provider' => 'nullable|string|in:flutterwave,paystack',
            'reference' => 'nullable|string',
            'tx_ref' => 'nullable|string',
            'description' => 'nullable|string',
            'currency' => 'nullable|string|size:3',
            'customer_email' => 'required|email',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'callback_url' => 'nullable|url',
            'meta' => 'nullable|array',
        ]);

        $reference = $data['reference'] ?? $data['tx_ref'] ?? ('PAY-' . strtoupper(bin2hex(random_bytes(6))));

        return $this->buildPaymentResponse(
            type: 'general',
            amount: (float) $data['amount'],
            reference: $reference,
            provider: $data['provider'] ?? $this->paymentManager->getDefaultProvider(),
            customer: [
                'email' => $data['customer_email'],
                'name' => $data['customer_name'],
                'phone' => $data['customer_phone'] ?? null,
            ],
            meta: $data['meta'] ?? [],
            description: $data['description'] ?? 'Payment',
            callbackUrl: $data['callback_url'] ?? null,
            currency: $data['currency'] ?? 'NGN',
        );
    }

    public function initializeBooking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'provider' => 'nullable|string|in:flutterwave,paystack',
        ]);

        $booking = Booking::findOrFail($data['booking_id']);

        return $this->buildPaymentResponse(
            type: 'booking',
            amount: (float) $booking->fresh()->total_amount,
            reference: $booking->booking_code,
            provider: $data['provider'] ?? $this->paymentManager->getDefaultProvider(),
            customer: [
                'email' => $booking->guest_email,
                'name' => $booking->guest_name,
            ],
            meta: [
                'booking_id' => $booking->id,
            ],
            description: 'Room Booking Payment',
            callbackUrl: route('booking.payment.callback', $booking),
        );
    }

    public function initializeByReference(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'reference' => 'required|string',
                'provider' => 'nullable|string|in:flutterwave,paystack',
            ]);

            $reference = $request->string('reference')->value();
            $provider = $request->input('provider') ?? $this->paymentManager->getDefaultProvider();
            $eventCallbackUrl = route('events.payment.callback', ['reference' => $reference]);

            if ($ticket = EventTicket::with(['ticketType', 'event'])->where('qr_code', $reference)->first()) {
                return $this->buildPaymentResponse(
                    'ticket',
                    (float) $ticket->amount,
                    $reference,
                    $provider,
                    [
                        'email' => $ticket->guest_email,
                        'name' => $ticket->guest_name,
                        'phone' => $ticket->guest_phone,
                    ],
                    [
                        'event' => $ticket->event->title,
                        'ticketType' => $ticket->ticketType->name,
                        'quantity' => $ticket->quantity,
                    ],
                    "Event Ticket: {$ticket->event->title}",
                    $eventCallbackUrl
                );
            }

            if ($reservation = EventTableReservation::with('event')->where('qr_code', $reference)->first()) {
                return $this->buildPaymentResponse(
                    'table',
                    (float) $reservation->amount,
                    $reference,
                    $provider,
                    [
                        'email' => $reservation->guest_email,
                        'name' => $reservation->guest_name,
                        'phone' => $reservation->guest_phone,
                    ],
                    [
                        'event' => $reservation->event->title,
                        'table' => $reservation->table_number ?? 'Table',
                    ],
                    "Table Reservation: {$reservation->event->title}",
                    $eventCallbackUrl
                );
            }

            if ($booking = Booking::where('booking_code', $reference)->first()) {
                return $this->buildPaymentResponse(
                    'booking',
                    (float) $booking->total_amount,
                    $reference,
                    $provider,
                    [
                        'email' => $booking->guest_email,
                        'name' => $booking->guest_name,
                    ],
                    [
                        'booking_id' => $booking->id,
                    ],
                    'Room Booking Payment',
                    route('booking.payment.callback', $booking)
                );
            }

            Log::warning('Payment reference not found', ['reference' => $reference]);

            return response()->json([
                'success' => false,
                'error' => 'Payment reference not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Payment initialization by reference failed', [
                'error' => $e->getMessage(),
                'reference' => $request->input('reference'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed',
            ], 422);
        }
    }

    public function verify(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'reference' => 'required|string',
                'provider' => 'nullable|string|in:flutterwave,paystack',
            ]);

            $reference = $request->string('reference')->value();
            $provider = $request->input('provider');
            $verification = $this->paymentManager->verifyPayment($reference, $provider);

            if (! ($verification['success'] ?? false) || ! ($verification['verified'] ?? false)) {
                Log::warning('Payment verification failed', [
                    'reference' => $reference,
                    'provider' => $provider,
                    'verification' => $verification,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Payment verification failed',
                ], 422);
            }

            return $this->confirmPayment($reference, $verification['provider'] ?? ($provider ?? $this->paymentManager->getDefaultProvider()));
        } catch (\Throwable $e) {
            Log::error('Payment verification error', [
                'error' => $e->getMessage(),
                'reference' => $request->input('reference'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment verification failed',
            ], 422);
        }
    }

    public function initializePublicOrder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:orders,id',
                'provider' => 'nullable|string|in:flutterwave,paystack',
            ]);

            $order = Order::findOrFail($request->input('order_id'));

            if ($order->payment_method !== 'online' || $order->payment_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid order for payment',
                ], 422);
            }

            return $this->buildPaymentResponse(
                type: 'public_order',
                amount: (float) $order->total,
                reference: $order->order_code,
                provider: $request->input('provider') ?? $this->paymentManager->getDefaultProvider(),
                customer: [
                    'email' => 'guest@hotel.com',
                    'name' => 'Guest Customer',
                ],
                meta: [
                    'order_id' => $order->id,
                    'department' => $order->department,
                ],
                description: ucfirst($order->department) . ' Order - ' . $order->order_code,
                callbackUrl: route('public.payment.callback', ['order' => $order->id]),
            );
        } catch (\Throwable $e) {
            Log::error('Public order payment initialization failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed',
            ], 422);
        }
    }

    public function store(Request $request, Booking $booking)
    {
        $this->authorize('create', Payment::class);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $reference = $data['reference'] ?? ('MANUAL-' . $booking->id . '-' . time());

        $payment = $booking->payments()->create([
            'amount' => $data['amount'],
            'amount_paid' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'method' => 'manual',
            'reference' => $reference,
            'payment_reference' => $reference,
            'transaction_ref' => $reference,
            'status' => $data['status'] ?? 'completed',
            'provider' => 'manual',
            'payment_type' => 'booking',
            'meta' => ['provider' => 'manual', 'reference' => $reference],
            'verified_at' => now(),
            'paid_at' => now(),
        ]);

        Log::info('Manual payment recorded', [
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
        ]);

        return back()->with('success', 'Payment recorded successfully.');
    }

    private function confirmPayment(string $reference, string $provider): JsonResponse
    {
        try {
            if (EventTicket::where('qr_code', $reference)->exists()) {
                $this->eventService->confirmPayment($reference, $provider, 'paid');

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed for event ticket',
                    'type' => 'ticket',
                    'reference' => $reference,
                ]);
            }

            if (EventTableReservation::where('qr_code', $reference)->exists()) {
                $this->eventService->confirmPayment($reference, $provider, 'paid');

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed for table reservation',
                    'type' => 'reservation',
                    'reference' => $reference,
                ]);
            }

            if ($booking = Booking::where('booking_code', $reference)->first()) {
                $booking->update([
                    'payment_status' => 'paid',
                    'payment_method' => $provider,
                    'status' => 'confirmed',
                    'expires_at' => null,
                ]);

                $payment = Payment::updateOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'reference' => $reference,
                    ],
                    [
                        'method' => $provider,
                        'payment_reference' => $reference,
                        'transaction_ref' => $reference,
                        'provider' => $provider,
                        'amount' => $booking->total_amount,
                        'amount_paid' => $booking->total_amount,
                        'currency' => 'NGN',
                        'status' => 'completed',
                        'payment_type' => 'booking',
                        'meta' => ['provider' => $provider, 'reference' => $reference],
                        'verified_at' => now(),
                        'paid_at' => now(),
                    ]
                );

                $this->paymentAccountingService->handleSuccessful($payment);

                Log::info('Booking payment confirmed', [
                    'booking_id' => $booking->id,
                    'reference' => $reference,
                    'provider' => $provider,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed for room booking',
                    'type' => 'booking',
                    'booking_id' => $booking->id,
                ]);
            }

            if ($payment = $this->findPaymentByReference($reference)) {
                $payment->update([
                    'provider' => $provider,
                    'status' => 'completed',
                    'verified_at' => now(),
                    'paid_at' => now(),
                ]);

                $this->paymentAccountingService->handleSuccessful($payment);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed',
                    'type' => $payment->payment_type,
                    'payment_id' => $payment->id,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Payment record not found',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Payment confirmation error', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment confirmation failed',
            ], 422);
        }
    }

    private function buildPaymentResponse(
        string $type,
        float $amount,
        string $reference,
        string $provider,
        array $customer = [],
        array $meta = [],
        string $description = '',
        ?string $callbackUrl = null,
        string $currency = 'NGN',
    ): JsonResponse {
        try {
            $provider = $this->paymentManager->isProviderEnabled($provider)
                ? $provider
                : $this->paymentManager->getDefaultProvider();

            $availableProviders = collect($this->paymentManager->getAvailablePaymentMethods())
                ->unique('value')
                ->values()
                ->all();

            $this->upsertPendingPaymentRecord(
                type: $type,
                reference: $reference,
                provider: $provider,
                amount: $amount,
                currency: $currency,
                meta: $meta,
            );

            $response = [
                'success' => true,
                'type' => $type,
                'reference' => $reference,
                'tx_ref' => $reference,
                'provider' => $provider,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'callback_url' => $callbackUrl,
                'customer' => $customer,
                'meta' => array_merge($meta, [
                    'type' => $type,
                    'reference' => $reference,
                ]),
                'show_provider_options' => $this->paymentManager->shouldShowProviderOptions(),
                'available_providers' => $availableProviders,
            ];

            if ($publicKey = $this->paymentManager->getPublicKey($provider)) {
                $response['public_key'] = $publicKey;
            }

            if ($provider === 'flutterwave') {
                $response['payment_options'] = 'card,banktransfer,ussd';
            }

            if ($provider === 'paystack') {
                $response['access_code'] = null;
                $response['authorization_url'] = null;
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Payment response building failed', [
                'error' => $e->getMessage(),
                'type' => $type,
                'reference' => $reference,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed',
            ], 422);
        }
    }

    private function upsertPendingPaymentRecord(
        string $type,
        string $reference,
        string $provider,
        float $amount,
        string $currency,
        array $meta,
    ): void {
        $bookingId = $meta['booking_id'] ?? null;

        Payment::updateOrCreate(
            [
                'reference' => $reference,
            ],
            [
                'booking_id' => $bookingId,
                'method' => $provider,
                'payment_reference' => $reference,
                'transaction_ref' => $reference,
                'provider' => $provider,
                'amount' => $amount,
                'amount_paid' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'payment_type' => $type,
                'meta' => $meta,
                'raw_response' => $meta,
            ]
        );
    }

    private function findPaymentByReference(string $reference): ?Payment
    {
        return Payment::query()
            ->where('reference', $reference)
            ->orWhere('payment_reference', $reference)
            ->orWhere('flutterwave_tx_ref', $reference)
            ->orWhere('external_reference', $reference)
            ->first();
    }
}
