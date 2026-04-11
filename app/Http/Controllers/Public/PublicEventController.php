<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Services\EventService;
use App\Services\PaymentProviderManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicEventController extends Controller
{
    public function __construct(
        protected EventService $eventService,
        protected PaymentProviderManager $paymentManager,
    ) {}

    /* ===================== EVENTS ===================== */

    public function index()
    {
        return Inertia::render('Public/Events', [
            'featuredEvents' => $this->eventService->getFeaturedEvents(),
            'allEvents'      => $this->eventService->getAvailableEvents(),
        ]);
    }

    public function show(Event $event)
    {
        $event->load([
            'ticketTypes' => fn ($q) => $q->where('is_active', true)->orderBy('price'),
            'promotionalMedia' => fn ($q) => $q->active()->ordered(),
        ]);

        return Inertia::render('Public/EventDetail', [
            'event' => $event,
        ]);
    }

    /* ===================== TICKETS ===================== */

    public function showTicketPurchase(Event $event)
    {
        return Inertia::render('Public/EventTicketPurchase', [
            'event'       => $event,
            'ticketTypes' => $event->ticketTypes()
                ->where('is_active', true)
                ->orderBy('price')
                ->get(),
        ]);
    }

    public function processTicketPurchase(Request $request, Event $event)
    {
        $data = $request->validate([
            'ticket_type_id' => 'required|exists:event_ticket_types,id',
            'guest_name'     => 'required|string|max:255',
            'guest_email'    => 'required|email|max:255',
            'guest_phone'    => 'nullable|string|max:20',
            'quantity'       => 'required|integer|min:1|max:10',
            'payment_method' => 'required|in:online,cash',
        ]);

        $ticket = $this->eventService->purchaseTicket($event, $data);

        if ($data['payment_method'] === 'cash') {
            $this->eventService->confirmPayment($ticket->qr_code, 'cash', 'paid');

            return redirect()->route('events.purchase.success', [
                'reference' => $ticket->qr_code,
            ]);
        }

        return redirect()->route('events.payment.process', [
            'reference' => $ticket->qr_code,
        ]);
    }

    /* ===================== TABLES ===================== */

    public function showTableReservation(Event $event)
    {
        abort_unless($event->has_table_reservations, 404);

        $event->load(['tableTypes' => fn ($q) => $q->orderBy('price')]);

        return Inertia::render('Public/EventTableReservation', [
            'event' => $event,
        ]);
    }

    public function processTableReservation(Request $request, Event $event)
    {
        $data = $request->validate([
            'guest_name'    => 'required|string|max:255',
            'guest_email'   => 'required|email|max:255',
            'guest_phone'   => 'required|string|max:20',
            'table_type_id' => 'required|exists:event_table_types,id',
        ]);

        $tableType = $event->tableTypes()
            ->where('id', $data['table_type_id'])
            ->firstOrFail();

        $data['payment_method'] = 'online';
        $data['amount'] = $tableType->price;

        $reservation = $this->eventService->reserveTable($event, $data);

        return redirect()->route('events.payment.process', [
            'reference' => $reservation->qr_code,
        ]);
    }

    /* ===================== PAYMENT ===================== */

    public function paymentProcess(Request $request)
    {
        $reference = $request->query('reference');

        $ticket = EventTicket::with(['event', 'ticketType'])
            ->where('qr_code', $reference)
            ->first();

        if ($ticket) {
            // Use the stored amount which includes taxes (1.5% VAT + 1% service charge)
            $amount = $ticket->amount;

            return Inertia::render('Public/PaymentProcess', [
                'type'      => 'ticket',
                'reference' => $reference,
                'amount'    => (float)$amount,
                'customer'  => [
                    'email' => $ticket->guest_email,
                    'name'  => $ticket->guest_name,
                    'phone' => $ticket->guest_phone,
                ],
                'meta' => [
                    'event'      => $ticket->event->title,
                    'ticketType' => $ticket->ticketType->name,
                    'quantity'   => $ticket->quantity,
                ],
            ]);
        }

        $reservation = EventTableReservation::with(['event',])
            ->where('qr_code', $reference)
            ->first();

        if ($reservation) {

        //dd($reservation);
            return Inertia::render('Public/PaymentProcess', [
                'type'      => 'table',
                'reference' => $reference,
                'amount'    => (float)($reservation->amount),
                'customer'  => [
                    'email' => $reservation->guest_email,
                    'name'  => $reservation->guest_name,
                    'phone' => $reservation->guest_phone,
                ],
                'meta' => [
                    'event' => $reservation->event->title,
                    'table' => $reservation->table_number ?? 'Table',
                ],
            ]);
        }

        abort(404);
    }

    public function paymentCallback(Request $request)
    {
        $request->validate([
            'tx_ref' => 'nullable|string',
            'reference' => 'nullable|string',
            'provider' => 'nullable|string|in:flutterwave,paystack',
        ]);

        $reference = $request->input('tx_ref') ?? $request->input('reference');
        abort_unless($reference, 422, 'Payment reference is required.');

        $provider = strtolower((string) ($request->input('provider') ?? config('payment.default', 'flutterwave')));
        $verification = $this->paymentManager->verifyPayment($reference, $provider);

        if (($verification['success'] ?? false) && ($verification['verified'] ?? false)) {
            $resolvedProvider = $verification['provider'] ?? $provider;
            $this->eventService->confirmPayment($reference, $resolvedProvider, 'paid');

            if (EventTicket::where('qr_code', $reference)->exists()) {
                return redirect()->route('events.purchase.success', ['reference' => $reference])
                    ->with('success', 'Payment confirmed successfully.');
            }

            if (EventTableReservation::where('qr_code', $reference)->exists()) {
                return redirect()->route('events.reservation.success', ['reference' => $reference])
                    ->with('success', 'Payment confirmed successfully.');
            }
        }

        $this->eventService->confirmPayment($reference, $provider, 'failed');

        return redirect()->route('events.payment.failed', ['reference' => $reference])
            ->with('error', 'We could not confirm your payment yet.');
    }

    public function purchaseSuccess(Request $request)
    {
        $reference = $request->query('reference');
        $ticket = EventTicket::with(['event', 'ticketType'])
            ->where('qr_code', $reference)
            ->orWhere('payment_reference', $reference)
            ->firstOrFail();

        return Inertia::render('Public/TicketPurchaseSuccess', [
            'ticket' => $ticket,
        ]);
    }

    public function reservationSuccess(Request $request)
    {
        $reference = $request->query('reference');
        $reservation = EventTableReservation::with('event')
            ->where('qr_code', $reference)
            ->orWhere('payment_reference', $reference)
            ->firstOrFail();

        return Inertia::render('Public/TableReservationSuccess', [
            'reservation' => $reservation,
        ]);
    }

    public function paymentFailed(Request $request)
    {
        $reference = $request->query('reference');

        return redirect()->route('events.payment.process', ['reference' => $reference])
            ->with('error', 'Payment was not confirmed. Please try again or contact support.');
    }

    /* ===================== CHECK-IN ===================== */

    public function checkIn(Request $request)
    {
        $qr = $request->get('qr');

        $ticket = EventTicket::where('qr_code', $qr)->first();
        if ($ticket) {
            return Inertia::render('Public/CheckIn', [
                'type'    => 'ticket',
                'item'    => $this->eventService->checkInTicket($qr),
                'success' => true,
            ]);
        }

        $reservation = EventTableReservation::where('qr_code', $qr)->first();
        if ($reservation) {
            return Inertia::render('Public/CheckIn', [
                'type'    => 'table',
                'item'    => $this->eventService->checkInTableReservation($qr),
                'success' => true,
            ]);
        }

        return Inertia::render('Public/CheckIn', [
            'error'   => 'Invalid QR code',
            'qr_code' => $qr,
        ]);
    }
}
