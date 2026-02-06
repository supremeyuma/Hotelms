<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\RoomServiceService;
use App\Services\MaintenanceService;
use App\Services\BillingService;
use App\Services\BookingExtensionService;
use App\Services\CheckoutService;
use App\Services\PaymentProviderManager;
use App\Events\ServiceRequested;
use App\Events\MaintenanceReported;
use App\Events\BillingUpdated;
use App\Models\LaundryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Receipt;
use App\Models\RoomCleaning;
use App\Models\Payment;

class RoomDashboardController extends Controller
{
protected RoomServiceService $roomService;
    protected MaintenanceService $maintenanceService;
    protected BillingService $billingService;
    protected BookingExtensionService $extensionService;
    protected CheckoutService $checkoutService;
    protected PaymentProviderManager $paymentManager;

    public function __construct(
        RoomServiceService $roomService,
        MaintenanceService $maintenanceService,
        BillingService $billingService,
        BookingExtensionService $extensionService,
        CheckoutService $checkoutService,
        PaymentProviderManager $paymentManager
    ) {
        $this->roomService = $roomService;
        $this->maintenanceService = $maintenanceService;
        $this->billingService = $billingService;
        $this->extensionService = $extensionService;
        $this->checkoutService = $checkoutService;
        $this->paymentManager = $paymentManager;
    }

    /**
     * Guest room dashboard
     */
    public function index(Request $request)
    {
        $room = $request->room;
        $booking = $request->booking;

        return Inertia::render('Guest/RoomDashboard', [
            'room' => $room,
            'booking' => $booking,
            'accessToken' => $room->roomAccessToken?->token,
            'outstandingBill' => $this->billingService
                ->outstandingForRoom($booking, $room->id),
            'laundryItems' => LaundryItem::all(),
            'cleaningStatus' => RoomCleaning::where('room_id', $room->id)
                ->whereNull('cleaned_at')
                ->latest()
                ->value('status'),
            'orders' => Order::where('room_id', $room->id)
                ->where('booking_id', $booking->id)
                ->get(),
            'showOrders' => request()->boolean('showOrders'),
        ]);
    }

    /**
     * ROOM-SPECIFIC outstanding balance
     */
    protected function outstandingForRoom($room): float
    {
        $charges = $room->charges()->sum('amount');
        $payments = $room->payments()->sum('amount');

        return max($charges - $payments, 0);
    }

    /**
     * Guest bill history (ROOM-SCOPED)
     */
    public function billHistory(Request $request)
    {
        $room = $request->room;
        $booking = $request->booking;

        return response()->json([
            'history' => $room->charges()
                ->select('id', 'description', 'amount', 'created_at')
                ->get()
                ->map(fn ($c) => [
                    'type' => 'charge',
                    'description' => $c->description,
                    'amount' => $c->amount,
                    'created_at' => $c->created_at,
                ])
                ->merge(
                    $room->payments()
                        ->select('id', 'amount', 'created_at')
                        ->get()
                        ->map(fn ($p) => [
                            'type' => 'payment',
                            'description' => 'Payment',
                            'amount' => $p->amount,
                            'created_at' => $p->created_at,
                        ])
                )
                ->sortBy('created_at')
                ->values(),
            'outstanding' => $this->billingService
                ->outstandingForRoom($booking, $room->id),
            'currency' => 'NGN',
        ]);
    }

/**
     * Initialize payment for room bill
     */
    public function initializeBillPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'provider' => 'nullable|string|in:flutterwave,paystack',
        ]);

        $room = $request->room;
        $booking = $request->booking;
        $amount = $request->amount;
        $provider = $request->provider ?? $this->paymentManager->getDefaultProvider();

        // Generate unique reference for bill payment
        $reference = 'BILL-' . $booking->id . '-' . $room->id . '-' . strtoupper(uniqid());

        try {
            // Build payment response for room bill
            return response()->json($this->buildBillPaymentResponse([
                'type' => 'room_bill',
                'amount' => $amount,
                'reference' => $reference,
                'provider' => $provider,
                'customer' => [
                    'email' => $booking->guest_email,
                    'name' => $booking->guest_name,
                ],
                'meta' => [
                    'booking_id' => $booking->id,
                    'room_id' => $room->id,
                    'payment_type' => 'room_bill',
                ],
                'description' => "Room Bill Payment - Room {$room->room_number}",
                'callbackUrl' => route('guest.bill.payment.callback', [
                    'room' => $room->id,
                    'reference' => $reference,
                ]),
            ]));
        } catch (\Exception $e) {
            Log::error('Bill payment initialization failed', [
                'error' => $e->getMessage(),
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'amount' => $amount,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed',
            ], 422);
        }
    }

    /**
     * Initialize payment for room bill using payment gateway
     * Replaces legacy manual payment method with real payment provider integration
     */
    public function payBill(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'provider' => 'nullable|string|in:flutterwave,paystack',
        ]);

        $room = $request->room;
        $booking = $request->booking;
        $amount = (float) $request->amount;
        $provider = $request->provider ?? $this->paymentManager->getDefaultProvider();

        // Generate unique reference for bill payment
        $reference = 'BILL-' . $booking->id . '-' . $room->id . '-' . strtoupper(uniqid());

        try {
            // Check if reference already exists
            if (DB::table('payments')->where('reference', $reference)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment reference conflict. Please try again.',
                ], 409);
            }

            // Initialize payment via payment provider manager
            $paymentInitiation = $this->paymentManager->initializePayment($provider, [
                'email' => $booking->guest_email,
                'name' => $booking->guest_name,
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'metadata' => [
                    'booking_id' => $booking->id,
                    'room_id' => $room->id,
                    'payment_type' => 'room_bill',
                    'guest_name' => $booking->guest_name,
                    'room_number' => $room->room_number,
                ],
                'description' => "Room Bill Payment - Room {$room->room_number}",
            ]);

            if (!$paymentInitiation['success']) {
                Log::warning('Room bill payment initialization failed', [
                    'booking_id' => $booking->id,
                    'room_id' => $room->id,
                    'amount' => $amount,
                    'provider' => $provider,
                    'error' => $paymentInitiation['error'] ?? 'Unknown error',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $paymentInitiation['error'] ?? 'Payment initialization failed',
                ], 422);
            }

            // Create payment record in database for tracking
            $payment = Payment::create([
                'user_id' => $booking->user_id,
                'reference' => $reference,
                'provider' => $provider,
                'amount' => $amount,
                'currency' => 'NGN',
                'status' => 'pending',
                'payment_type' => 'room_bill',
                'metadata' => json_encode([
                    'booking_id' => $booking->id,
                    'room_id' => $room->id,
                ]),
            ]);

            Log::info('Room bill payment initiated', [
                'payment_id' => $payment->id,
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'reference' => $reference,
                'provider' => $provider,
                'amount' => $amount,
            ]);

            // Build response based on provider
            $response = [
                'success' => true,
                'reference' => $reference,
                'tx_ref' => $reference,
                'provider' => $provider,
                'amount' => $amount,
                'currency' => 'NGN',
                'description' => "Room Bill Payment - Room {$room->room_number}",
                'customer' => [
                    'email' => $booking->guest_email,
                    'name' => $booking->guest_name,
                ],
            ];

            // Add provider-specific data
            if ($provider === 'flutterwave') {
                $response['payment_options'] = 'card,banktransfer,ussd';
                $response['public_key'] = $this->paymentManager->getPublicKey('flutterwave');
            } elseif ($provider === 'paystack') {
                $response['public_key'] = $this->paymentManager->getPublicKey('paystack');
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Room bill payment error', [
                'error' => $e->getMessage(),
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'amount' => $amount,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment initialization failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Handle payment callback for room bills
     */
    public function handleBillPaymentCallback(Request $request, $room, $reference)
    {
        try {
            $verification = $this->paymentManager->verifyPayment($reference);

            if (!$verification['success']) {
                Log::warning('Bill payment verification failed', [
                    'reference' => $reference,
                    'room_id' => $room,
                ]);

                return redirect()->route('guest.room.dashboard', ['token' => $request->room->roomAccessToken->token])
                    ->with('error', 'Payment verification failed');
            }

            // Find the room and booking
            $roomModel = \App\Models\Room::findOrFail($room);
            $payment = Payment::where('reference', $reference)->first();

            if ($payment) {
                // Update payment status
                $payment->update([
                    'status' => 'completed',
                    'verified_at' => now(),
                ]);

                // Add payment to billing system
                $booking = $payment->booking;
                $this->billingService->addPayment(
                    booking: $booking,
                    roomId: $roomModel->id,
                    amount: $payment->amount,
                    method: $payment->provider,
                    reference: $reference
                );

                \Log::info('Bill payment confirmed', [
                    'reference' => $reference,
                    'room_id' => $room,
                    'amount' => $payment->amount,
                ]);
            }

            return redirect()->route('guest.room.dashboard', ['token' => $request->room->roomAccessToken->token])
                ->with('success', 'Payment completed successfully');

        } catch (\Exception $e) {
            \Log::error('Bill payment callback error', [
                'error' => $e->getMessage(),
                'reference' => $reference,
                'room_id' => $room,
            ]);

            return redirect()->route('guest.room.dashboard', ['token' => $request->room->roomAccessToken->token])
                ->with('error', 'Payment processing failed');
        }
    }

    /**
     * Build standardized payment response for room bills
     */
    private function buildBillPaymentResponse(array $paymentData): array
    {
        try {
            $showProviderOptions = $this->paymentManager->shouldShowProviderOptions();
            $availableProviders = $this->paymentManager->getAvailablePaymentMethods();
            
            // Get provider-specific public key
            $publicKey = $this->paymentManager->getPublicKey($paymentData['provider']);

            $response = [
                'success' => true,
                'type' => $paymentData['type'],
                'reference' => $paymentData['reference'],
                'tx_ref' => $paymentData['reference'],
                'provider' => $paymentData['provider'],
                'amount' => $paymentData['amount'],
                'currency' => 'NGN',
                'description' => $paymentData['description'],
                'callback_url' => $paymentData['callbackUrl'],
                'customer' => $paymentData['customer'],
                'meta' => $paymentData['meta'],
                'show_provider_options' => $showProviderOptions,
                'available_providers' => $availableProviders,
            ];

            if ($publicKey) {
                $response['public_key'] = $publicKey;
            }

            // Add provider-specific initialization data
            if ($paymentData['provider'] === 'flutterwave') {
                $response['payment_options'] = 'card,banktransfer,ussd';
                $response['tx_ref'] = $paymentData['reference'];
            } elseif ($paymentData['provider'] === 'paystack') {
                $response['access_code'] = null;
                $response['authorization_url'] = null;
            }

            return $response;

        } catch (\Exception $e) {
            \Log::error('Bill payment response building failed', [
                'error' => $e->getMessage(),
                'reference' => $paymentData['reference'] ?? null,
            ]);

            return [
                'success' => false,
                'error' => 'Payment initialization failed',
            ];
        }
    }


    /* ======================
       EXISTING METHODS BELOW
       ====================== */

    public function serviceRequest(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:cleaning,kitchen,bar,laundry',
            'notes' => 'nullable|string|max:500',
        ]);

        $serviceRequest = $this->roomService->createRequest(
            $request->booking,
            $request->type,
            ['notes' => $request->notes, 'room_id' => $request->room->id],
        );

        event(new ServiceRequested($serviceRequest));

        return back()->with('success', 'Service request submitted.');
    }

    public function reportMaintenance(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:plumbing,electrical,furniture,other',
            'description' => 'required|string|max:1000',
        ]);

        $ticket = $this->maintenanceService->reportIssue(
            $request->booking,
            $request->type,
            $request->description
        );

        event(new MaintenanceReported($ticket));

        return back()->with('success', 'Maintenance issue reported.');
    }

    public function extendStay(Request $request)
    {
        $request->validate([
            'new_checkout' => 'required|date|after:' . $request->booking->check_out,
        ]);

        $this->extensionService->extendStay(
            $request->booking,
            $request->new_checkout
        );

        return back()->with('success', 'Stay extended successfully.');
    }

    public function checkout(Request $request)
    {
        $this->checkoutService->checkout($request->booking);

        return back()->with('success', 'Checked out successfully.');
    }
}