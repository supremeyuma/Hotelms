<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Services\PaymentProviderManager;
use App\Services\AuditLogger;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * PaymentController: Production-ready multi-provider payment handling
 * Supports Flutterwave, Paystack with intelligent provider selection
 */
class PaymentController extends Controller
{
    public function __construct(
        protected PaymentProviderManager $paymentManager,
        protected EventService $eventService
    ) {}

    /**
     * Initialize payment for standard bookings (rooms, invoices, etc.)
     * Returns available payment methods and initialization data
     */
    public function initialize(Request $request)
    {
        try {
            $data = $request->validate([
                'booking_id'      => 'nullable|exists:bookings,id',
                'room_id'         => 'nullable|exists:rooms,id',
                'amount'          => 'required|numeric|min:1',
                'tx_ref'          => 'nullable|string|max:191',
                'description'     => 'nullable|string|max:1000',
                'customer_email'  => 'nullable|email',
                'customer_name'   => 'nullable|string|max:255',
                'provider'        => 'nullable|string|in:flutterwave,paystack',
            ]);

            $reference = $data['tx_ref'] ?? ('PAY-' . Str::uuid());

            $payment = Payment::create([
                'booking_id' => $data['booking_id'] ?? null,
                'room_id'    => $data['room_id'] ?? null,
                'amount'     => $data['amount'],
                'currency'   => 'NGN',
                'reference'  => $reference,
                'status'     => 'pending',
                'provider'   => $data['provider'] ?? $this->paymentManager->getDefaultProvider(),
            ]);

            // Determine which provider to use
            $provider = $data['provider'] ?? $this->paymentManager->getDefaultProvider();
            $showProviderOptions = $this->paymentManager->shouldShowProviderOptions();

            return response()->json([
                'success' => true,
                'reference' => $reference,
                'amount' => $payment->amount,
                'currency' => 'NGN',
                'provider' => $provider,
                'show_provider_options' => $showProviderOptions,
                'available_providers' => $this->paymentManager->getAvailablePaymentMethods(),
                'public_key' => $this->paymentManager->getPublicKey($provider),
                'description' => $data['description'] ?? null,
                'customer' => [
                    'email' => $data['customer_email']
                        ?? $request->user()?->email
                        ?? 'guest@hotel.com',
                    'name'  => $data['customer_name']
                        ?? $request->user()?->name
                        ?? 'Hotel Guest',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Payment initialization failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed',
            ], 422);
        }
    }

    /**
     * Initialize payment by reference (EVENTS, TABLES, BOOKINGS)
     * Returns provider-specific initialization data
     */
    public function initializeByReference(Request $request)
    {
        try {
            $request->validate([
                'reference' => 'required|string',
                'provider'  => 'nullable|string|in:flutterwave,paystack',
            ]);

            $reference = $request->reference;
            $provider = $request->provider ?? $this->paymentManager->getDefaultProvider();

            // Try to find by event ticket
            if ($ticket = EventTicket::with(['ticketType', 'event'])
                ->where('qr_code', $reference)
                ->first()) {

                return $this->buildPaymentResponse(
                    'ticket',
                    $ticket->amount,
                    $reference,
                    $provider,
                    [
                        'email' => $ticket->guest_email,
                        'name'  => $ticket->guest_name,
                        'phone' => $ticket->guest_phone,
                    ],
                    [
                        'event'      => $ticket->event->title,
                        'ticketType' => $ticket->ticketType->name,
                        'quantity'   => $ticket->quantity,
                    ],
                    "Event Ticket: {$ticket->event->title}"
                );
            }

            // Try to find by table reservation
            if ($reservation = EventTableReservation::with('event')
                ->where('qr_code', $reference)
                ->first()) {

                return $this->buildPaymentResponse(
                    'table',
                    $reservation->amount,
                    $reference,
                    $provider,
                    [
                        'email' => $reservation->guest_email,
                        'name'  => $reservation->guest_name,
                        'phone' => $reservation->guest_phone,
                    ],
                    [
                        'event' => $reservation->event->title,
                        'table' => $reservation->table_number ?? 'Table',
                    ],
                    "Table Reservation: {$reservation->event->title}"
                );
            }

            // Try to find by room booking
            if ($booking = Booking::where('reference', $reference)->first()) {
                return $this->buildPaymentResponse(
                    'booking',
                    $booking->total_amount,
                    $reference,
                    $provider,
                    [
                        'email' => $booking->guest_email,
                        'name'  => $booking->guest_name,
                    ],
                    [],
                    'Room Booking Payment'
                );
            }

            Log::warning('Payment reference not found', ['reference' => $reference]);

            return response()->json([
                'success' => false,
                'error' => 'Payment reference not found',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Payment initialization by reference failed', [
                'error' => $e->getMessage(),
                'reference' => $request->reference ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed',
            ], 422);
        }
    }

    /**
     * Verify payment with multi-provider support
     */
    public function verify(Request $request)
    {
        try {
            $request->validate([
                'reference' => 'required|string',
                'provider'  => 'nullable|string|in:flutterwave,paystack',
            ]);

            $reference = $request->reference;
            $provider = $request->provider;

            // Verify with specific provider or try all enabled providers
            $verification = $this->paymentManager->verifyPayment($reference, $provider);

            if (!$verification['success']) {
                Log::warning('Payment verification failed', [
                    'reference' => $reference,
                    'provider' => $provider,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Payment verification failed',
                ], 422);
            }

            // Payment verified - delegate to confirmation handler
            return $this->confirmPayment($reference, $verification['provider']);

        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'error' => $e->getMessage(),
                'reference' => $request->reference ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment verification failed',
            ], 422);
        }
    }

    /**
     * Confirm payment after successful verification
     */
    private function confirmPayment(string $reference, string $provider)
    {
        try {
            // Event Ticket
            if ($ticket = EventTicket::where('qr_code', $reference)->first()) {
                $result = $this->eventService->confirmPayment('ticket', $ticket->id, $provider);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed for event ticket',
                    'type' => 'ticket',
                    'data' => $result,
                ]);
            }

            // Table Reservation
            if ($reservation = EventTableReservation::where('qr_code', $reference)->first()) {
                $result = $this->eventService->confirmPayment('table', $reservation->id, $provider);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed for table reservation',
                    'type' => 'reservation',
                    'data' => $result,
                ]);
            }

            // Room Booking
            if ($booking = Booking::where('reference', $reference)->first()) {
                // Update booking payment status
                $booking->update([
                    'payment_status' => 'paid',
                    'payment_method' => $provider,
                    'paid_at' => now(),
                ]);

                // Record payment
                Payment::create([
                    'user_id' => $booking->user_id,
                    'reference' => $reference,
                    'provider' => $provider,
                    'amount' => $booking->total_amount,
                    'currency' => 'NGN',
                    'status' => 'completed',
                    'payment_type' => 'booking',
                    'verified_at' => now(),
                ]);

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

            Log::warning('Payment reference not found during confirmation', [
                'reference' => $reference,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment record not found',
            ], 404);

        } catch (\Exception $e) {
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

    /**
     * Manual / back-office payment storage
     */
    public function store(StorePaymentRequest $request, Booking $booking)
    {
        $this->authorize('create', Payment::class);

        $data = $request->validated();
        $data['provider'] = 'manual'; // Manual payments entered by staff
        
        $payment = $booking->payments()->create($data);

        AuditLogger::log('payment_recorded', 'Payment', $payment->id, [
            'booking_id' => $booking->id,
            'provider' => 'manual',
        ]);

        return back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Build standardized payment response for all transaction types
     */
    private function buildPaymentResponse(
        string $type,
        float $amount,
        string $reference,
        string $provider,
        array $customer = [],
        array $meta = [],
        string $description = ''
    ): \Illuminate\Http\JsonResponse {
        try {
            $showProviderOptions = $this->paymentManager->shouldShowProviderOptions();
            $availableProviders = $this->paymentManager->getAvailablePaymentMethods();
            
            // Get provider-specific public key
            $publicKey = $this->paymentManager->getPublicKey($provider);

            $response = [
                'success' => true,
                'type' => $type,
                'reference' => $reference,
                'tx_ref' => $reference, // Flutterwave requires tx_ref
                'provider' => $provider,
                'amount' => $amount,
                'currency' => 'NGN',
                'description' => $description,
                'customer' => $customer,
                'meta' => array_merge($meta, [
                    'type' => $type,
                    'reference' => $reference,
                ]),
                'show_provider_options' => $showProviderOptions,
                'available_providers' => $availableProviders,
            ];

            if ($publicKey) {
                $response['public_key'] = $publicKey;
            }

            // Add provider-specific initialization data
            if ($provider === 'flutterwave') {
                // Flutterwave requires these specific fields
                $response['payment_options'] = 'card,banktransfer,ussd';
                $response['tx_ref'] = $reference;
            } elseif ($provider === 'paystack') {
                // Paystack-specific fields
                $response['access_code'] = null; // Will be generated by PaystackService
                $response['authorization_url'] = null; // Will be generated by PaystackService
            }

            return response()->json($response);

        } catch (\Exception $e) {
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
}
