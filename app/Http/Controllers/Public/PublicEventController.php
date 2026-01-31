<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Services\EventService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicEventController extends Controller
{
    public function __construct(
        protected EventService $eventService
    ) {}

    public function index()
    {
        $featuredEvents = $this->eventService->getFeaturedEvents();
        $allEvents = $this->eventService->getAvailableEvents();

        return Inertia::render('Public/Events', [
            'featuredEvents' => $featuredEvents,
            'allEvents' => $allEvents,
        ]);
    }

    public function show(Event $event)
    {
        $event->load(['ticketTypes' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('price');
                }, 'promotionalMedia' => function ($query) {
                    $query->active()->ordered();
                }]);

        return Inertia::render('Public/EventDetail', [
            'event' => $event,
        ]);
    }

    public function showTicketPurchase(Event $event)
    {
        // Load the tickets and assign them to a variable
        $tickets = $event->ticketTypes()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return Inertia::render('Public/EventTicketPurchase', [
            'event' => $event,
            'ticketTypes' => $tickets, // Now $tickets is defined
        ]);
    }

    public function processTicketPurchase(Request $request, Event $event)
    {
        $data = $request->validate([
            'ticket_type_id' => 'required|exists:event_ticket_types,id',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'quantity' => 'required|integer|min:1|max:10',
            'payment_method' => 'required|in:online,cash',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $ticket = $this->eventService->purchaseTicket($event, $data);

            if ($data['payment_method'] === 'cash') {
                // For cash payments, mark as confirmed (payment collected at venue)
                $this->eventService->confirmPayment($ticket->qr_code, 'cash', 'paid');
                
                return redirect()->route('events.purchase.success', ['reference' => $ticket->qr_code])
                    ->with('success', 'Ticket purchased successfully!');
            } else {
                // For online payments, redirect to payment processor
                return redirect()->route('events.payment.process', ['reference' => $ticket->qr_code]);
            }

        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function showTableReservation(Event $event)
    {
        if (!$event->has_table_reservations) {
            abort(404, 'This event does not support table reservations');
        }

        $event->load(['tableTypes' => function ($query) {
                    $query->orderBy('price');
                }]);

        return Inertia::render('Public/EventTableReservation', [
            'event' => $event,
        ]);
    }

    public function processTableReservation(Request $request, Event $event)
    {
        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'required|email|max:255',
            'guest_phone' => 'required|string|max:20',
            'table_type_id' => 'required|exists:event_table_types,id',
        ]);

        // Verify table type belongs to this event
        $tableType = \App\Models\EventTableType::where('id', $data['table_type_id'])
            ->where('event_id', $event->id)
            ->first();

        if (!$tableType) {
            return back()
                ->with('error', 'Invalid table type selected')
                ->withInput();
        }

        $data['payment_method'] = 'online'; // Force online payment only
        $data['amount'] = $tableType->price;

        try {
            $reservation = $this->eventService->reserveTable($event, $data);

            // Always redirect to payment processor for online payments
            return redirect()->route('events.payment.process', ['reference' => $reservation->qr_code]);

        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function purchaseSuccess(Request $request)
    {
        $reference = $request->get('reference');
        $ticket = EventTicket::with(['event', 'ticketType'])
            ->where('qr_code', $reference)
            ->first();

        if (!$ticket) {
            abort(404, 'Ticket not found');
        }

        return Inertia::render('Public/TicketPurchaseSuccess', [
            'ticket' => $ticket,
        ]);
    }

    public function reservationSuccess(Request $request)
    {
        $reference = $request->get('reference');
        $reservation = EventTableReservation::with(['event'])
            ->where('qr_code', $reference)
            ->first();

        if (!$reservation) {
            abort(404, 'Reservation not found');
        }

        return Inertia::render('Public/TableReservationSuccess', [
            'reservation' => $reservation,
        ]);
    }

    public function paymentProcess(Request $request)
    {
        $reference = $request->get('reference');
        
        // Check if it's a ticket or table reservation
        $ticket = EventTicket::with(['event', 'ticketType'])
            ->where('qr_code', $reference)
            ->first();

        $reservation = EventTableReservation::with(['event'])
            ->where('qr_code', $reference)
            ->first();

        if ($ticket) {
            // Mock Flutterwave integration
            return Inertia::render('Public/PaymentProcess', [
                'type' => 'ticket',
                'item' => $ticket,
                'amount' => $ticket->amount_paid,
                'reference' => $reference,
            ]);
        } elseif ($reservation) {
            // Mock Flutterwave integration
            return Inertia::render('Public/PaymentProcess', [
                'type' => 'table',
                'item' => $reservation,
                'amount' => $reservation->amount_paid,
                'reference' => $reference,
            ]);
        } else {
            abort(404, 'Payment reference not found');
        }
    }

    public function paymentCallback(Request $request)
    {
        // Mock Flutterwave callback handling
        $reference = $request->input('tx_ref');
        $status = $request->input('status'); // success, failed
        
        $paymentMethod = $request->input('payment_method', 'online');
        
        if ($status === 'success') {
            $this->eventService->confirmPayment($reference, $paymentMethod, 'paid');
            
            return redirect()->route('events.purchase.success', ['reference' => $reference])
                ->with('success', 'Payment processed successfully!');
        } else {
            $this->eventService->confirmPayment($reference, $paymentMethod, 'failed');
            
            return redirect()->route('events.payment.failed', ['reference' => $reference])
                ->with('error', 'Payment failed. Please try again.');
        }
    }

    public function paymentFailed(Request $request)
    {
        $reference = $request->get('reference');
        
        return Inertia::render('Public/PaymentFailed', [
            'reference' => $reference,
        ]);
    }

    public function checkIn(Request $request)
    {
        $qrCode = $request->get('qr');
        
        // Check if it's a ticket or table reservation
        $ticket = EventTicket::with(['event', 'ticketType'])
            ->where('qr_code', $qrCode)
            ->first();

        $reservation = EventTableReservation::with(['event'])
            ->where('qr_code', $qrCode)
            ->first();

        if ($ticket) {
            try {
                $checkedTicket = $this->eventService->checkInTicket($qrCode);
                return Inertia::render('Public/CheckIn', [
                    'type' => 'ticket',
                    'item' => $checkedTicket,
                    'success' => true,
                ]);
            } catch (\Exception $e) {
                return Inertia::render('Public/CheckIn', [
                    'error' => $e->getMessage(),
                    'qr_code' => $qrCode,
                ]);
            }
        } elseif ($reservation) {
            try {
                $checkedReservation = $this->eventService->checkInTableReservation($qrCode);
                return Inertia::render('Public/CheckIn', [
                    'type' => 'table',
                    'item' => $checkedReservation,
                    'success' => true,
                ]);
            } catch (\Exception $e) {
                return Inertia::render('Public/CheckIn', [
                    'error' => $e->getMessage(),
                    'qr_code' => $qrCode,
                ]);
            }
        } else {
            return Inertia::render('Public/CheckIn', [
                'error' => 'Invalid QR code',
                'qr_code' => $qrCode,
            ]);
        }
    }
}