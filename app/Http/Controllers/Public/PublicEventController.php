<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PublicEventController extends Controller
{
    public function __construct(
        protected EventService $eventService
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
                'amount'    => $amount,
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

    public function flutterwaveInitialize(Request $request)
    {
        $data = $request->validate([
            'reference' => 'required|string',
            'amount'    => 'required|numeric|min:1',
            'email'     => 'required|email',
            'name'      => 'required|string',
        ]);

        $response = Http::withToken(config('services.flutterwave.secret_key'))
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref'   => $data['reference'],
                'amount'   => $data['amount'],
                'currency' => 'NGN',
                'redirect_url' => route('events.payment.callback'),
                'customer' => [
                    'email' => $data['email'],
                    'name'  => $data['name'],
                ],
                'customizations' => [
                    'title'       => 'MooreLife Resort',
                    'description' => 'Event Payment',
                ],
            ])
            ->throw()
            ->json();

        return response()->json($response['data']);
    }

    public function paymentCallback(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required',
            'tx_ref'         => 'required|string',
        ]);

        $verify = Http::withToken(config('services.flutterwave.secret_key'))
            ->get("https://api.flutterwave.com/v3/transactions/{$request->transaction_id}/verify")
            ->throw()
            ->json();

        if (
            $verify['status'] === 'success' &&
            $verify['data']['status'] === 'successful'
        ) {
            $this->eventService->confirmPayment(
                $request->tx_ref,
                'flutterwave',
                'paid',
                $verify['data']['id']
            );

            return redirect()->route('events.purchase.success', [
                'reference' => $request->tx_ref,
            ]);
        }

        $this->eventService->confirmPayment(
            $request->tx_ref,
            'flutterwave',
            'failed'
        );

        return redirect()->route('events.payment.failed', [
            'reference' => $request->tx_ref,
        ]);
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
