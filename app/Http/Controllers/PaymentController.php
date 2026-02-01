<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Services\FlutterwaveService;
use App\Services\AuditLogger;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected FlutterwaveService $flutterwave,
        protected EventService $eventService
    ) {}

    /**
     * Initialize payment for standard bookings (rooms, invoices, etc.)
     */
    public function initialize(Request $request)
    {
        $data = $request->validate([
            'booking_id'   => 'nullable|exists:bookings,id',
            'room_id'      => 'nullable|exists:rooms,id',
            'amount'       => 'required|numeric|min:1',
            'tx_ref'       => 'nullable|string|max:191',
            'description'  => 'nullable|string|max:1000',
            'customer_email' => 'nullable|email',
            'customer_name'  => 'nullable|string|max:255',
        ]);

        $reference = $data['tx_ref'] ?? ('PAY-' . Str::uuid());

        $payment = Payment::create([
            'booking_id' => $data['booking_id'] ?? null,
            'room_id'    => $data['room_id'] ?? null,
            'amount'     => $data['amount'],
            'currency'   => 'NGN',
            'reference'  => $reference,
            'status'     => 'pending',
        ]);

        return response()->json([
            'public_key' => config('flutterwave.public_key'),
            'tx_ref'     => $reference,
            'amount'     => $payment->amount,
            'currency'   => 'NGN',
            'description' => $data['description'] ?? null,
            'customer' => [
                'email' => $data['customer_email']
                    ?? $request->user()?->email
                    ?? 'guest@hotel.com',
                'name'  => $data['customer_name']
                    ?? $request->user()?->name
                    ?? 'Hotel Guest',
            ],
            'payment_options' => 'card,banktransfer,ussd',
        ]);
    }

    /**
     * Initialize payment by reference (EVENTS, TABLES, BOOKINGS)
     * Does NOT open Flutterwave – frontend handles checkout
     */
    public function initializeByReference(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $reference = $request->reference;

        // Event Ticket
        if ($ticket = EventTicket::with(['ticketType', 'event'])
            ->where('qr_code', $reference)
            ->first()) {

            return response()->json([
                'public_key' => config('flutterwave.public_key'),
                'tx_ref'     => $reference,
                'amount'     => $ticket->ticketType->price * $ticket->quantity,
                'currency'   => 'NGN',
                'description'=> "Event Ticket: {$ticket->event->title}",
                'customer'   => [
                    'email' => $ticket->guest_email,
                    'name'  => $ticket->guest_name,
                ],
                'payment_options' => 'card,banktransfer,ussd',
            ]);
        }

        // Table Reservation
        if ($reservation = EventTableReservation::with('event')
            ->where('qr_code', $reference)
            ->first()) {

            return response()->json([
                'public_key' => config('flutterwave.public_key'),
                'tx_ref'     => $reference,
                'amount'     => $reservation->amount,
                'currency'   => 'NGN',
                'description'=> "Table Reservation: {$reservation->event->title}",
                'customer'   => [
                    'email' => $reservation->guest_email,
                    'name'  => $reservation->guest_name,
                ],
                'payment_options' => 'card,banktransfer,ussd',
            ]);
        }

        // Room Booking
        if ($booking = Booking::where('reference', $reference)->first()) {
            return response()->json([
                'public_key' => config('flutterwave.public_key'),
                'tx_ref'     => $reference,
                'amount'     => $booking->outstanding_balance,
                'currency'   => 'NGN',
                'description'=> 'Room Booking Payment',
                'customer'   => [
                    'email' => $booking->guest_email,
                    'name'  => $booking->guest_name,
                ],
                'payment_options' => 'card,banktransfer,ussd,bank',
            ]);
        }

        abort(404, 'Invalid payment reference');
    }

    /**
     * Verify payment using FlutterwaveService
     * Single source of truth for verification
     */
    public function verify(string $reference)
    {
        $result = $this->flutterwave->verifyPayment($reference);

        if (!$result['verified']) {
            abort(422, $result['error'] ?? 'Payment verification failed');
        }

        // Update internal payment record if exists
        if ($payment = Payment::where('reference', $reference)->first()) {
            $payment->update([
                'status' => 'successful',
                'flutterwave_tx_id' => $result['data']['id'] ?? null,
                'raw_response' => $result['data'],
                'paid_at' => now(),
            ]);

            try {
                resolve(\App\Services\PaymentAccountingService::class)
                    ->handleSuccessful($payment);
            } catch (\Throwable $e) {
                // accounting failure must never block payment success
            }
        }

        // Always confirm event-related payments
        try {
            $this->eventService->confirmPayment($reference, 'flutterwave', 'paid');
        } catch (\Throwable $e) {
            // ignore if not an event payment
        }

        return redirect()->route('events.purchase.success', [
            'reference' => $reference,
        ]);
    }

    /**
     * Manual / back-office payment storage
     */
    public function store(StorePaymentRequest $request, Booking $booking)
    {
        $this->authorize('create', Payment::class);

        $payment = $booking->payments()->create($request->validated());

        AuditLogger::log('payment_recorded', 'Payment', $payment->id, [
            'booking_id' => $booking->id,
        ]);

        return back()->with('success', 'Payment recorded successfully.');
    }
}
