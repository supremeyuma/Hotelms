<?php
// app/Http/Controllers/Staff/EventCheckInController.php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventCheckInController extends Controller
{
    public function index()
    {
        $activeEvents = Event::where('is_active', true)
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now()->subHours(4))
            ->orderBy('start_datetime', 'desc')
            ->get(['id', 'title', 'start_datetime', 'end_datetime', 'venue']);

        return Inertia::render('Staff/Events/CheckIn', [
            'activeEvents' => $activeEvents,
        ]);
    }

    public function scan()
    {
        return Inertia::render('Staff/Events/Scan');
    }

    public function validate(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $qrCode = $request->qr_code;

        // Check if it's a ticket QR code (starts with ET_)
        if (str_starts_with($qrCode, 'ET_')) {
            return $this->validateTicket($qrCode);
        }

        // Check if it's a table reservation QR code (starts with TR_)
        if (str_starts_with($qrCode, 'TR_')) {
            return $this->validateTableReservation($qrCode);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid QR code format',
            'type' => 'error'
        ]);
    }

    private function validateTicket($qrCode)
    {
        $ticket = EventTicket::with([
            'event',
            'ticketType',
            'booking' => function ($query) {
                $query->with('guest');
            }
        ])->where('qr_code', $qrCode)->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
                'type' => 'error'
            ]);
        }

        // Check if ticket is already used
        if ($ticket->check_in_status) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket already used',
                'type' => 'warning',
                'data' => [
                    'checked_in_at' => $ticket->check_in_time,
                    'checked_in_by' => $ticket->checked_in_by,
                ]
            ]);
        }

        // Check if event has started
        if ($ticket->event->start_datetime > now()->addHours(2)) {
            return response()->json([
                'success' => false,
                'message' => 'Event has not started yet',
                'type' => 'warning'
            ]);
        }

        // Check if event has ended
        if ($ticket->event->end_datetime < now()->subHours(4)) {
            return response()->json([
                'success' => false,
                'message' => 'Event has ended',
                'type' => 'error'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Valid ticket',
            'type' => 'success',
            'data' => [
                'id' => $ticket->id,
                'event_title' => $ticket->event->title,
                'ticket_type' => $ticket->ticketType->name,
                'guest_name' => $ticket->booking->guest->full_name ?? 'Guest',
                'guest_email' => $ticket->booking->guest->email ?? '',
                'quantity' => $ticket->quantity,
                'unit_price' => $ticket->unit_price,
                'total_amount' => $ticket->total_amount,
                'event_venue' => $ticket->event->venue,
                'event_datetime' => $ticket->event->start_datetime,
            ]
        ]);
    }

    private function validateTableReservation($qrCode)
    {
        $reservation = EventTableReservation::with([
            'event',
            'booking' => function ($query) {
                $query->with('guest');
            }
        ])->where('qr_code', $qrCode)->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Table reservation not found',
                'type' => 'error'
            ]);
        }

        // Check if reservation is already used
        if ($reservation->check_in_status) {
            return response()->json([
                'success' => false,
                'message' => 'Table reservation already checked in',
                'type' => 'warning',
                'data' => [
                    'checked_in_at' => $reservation->check_in_time,
                    'checked_in_by' => $reservation->checked_in_by,
                ]
            ]);
        }

        // Check if event has started
        if ($reservation->event->start_datetime > now()->addHours(2)) {
            return response()->json([
                'success' => false,
                'message' => 'Event has not started yet',
                'type' => 'warning'
            ]);
        }

        // Check if event has ended
        if ($reservation->event->end_datetime < now()->subHours(4)) {
            return response()->json([
                'success' => false,
                'message' => 'Event has ended',
                'type' => 'error'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Valid table reservation',
            'type' => 'success',
            'data' => [
                'id' => $reservation->id,
                'event_title' => $reservation->event->title,
                'guest_name' => $reservation->booking->guest->full_name ?? 'Guest',
                'guest_email' => $reservation->booking->guest->email ?? '',
                'table_number' => $reservation->table_number,
                'guest_count' => $reservation->guest_count,
                'total_amount' => $reservation->total_amount,
                'special_requests' => $reservation->special_requests,
                'event_venue' => $reservation->event->venue,
                'event_datetime' => $reservation->event->start_datetime,
            ]
        ]);
    }

    public function checkIn(Request $request)
    {
        $request->validate([
            'type' => 'required|in:ticket,table',
            'id' => 'required|integer',
            'qr_code' => 'required|string',
        ]);

        try {
            \DB::beginTransaction();

            if ($request->type === 'ticket') {
                $ticket = EventTicket::where('id', $request->id)
                    ->where('qr_code', $request->qr_code)
                    ->first();

                if (!$ticket || $ticket->check_in_status) {
                    throw new \Exception('Ticket not found or already checked in');
                }

                $ticket->update([
                    'check_in_status' => true,
                    'check_in_time' => now(),
                    'checked_in_by' => auth()->id(),
                ]);

                $message = 'Ticket checked in successfully';
            } else {
                $reservation = EventTableReservation::where('id', $request->id)
                    ->where('qr_code', $request->qr_code)
                    ->first();

                if (!$reservation || $reservation->check_in_status) {
                    throw new \Exception('Table reservation not found or already checked in');
                }

                $reservation->update([
                    'check_in_status' => true,
                    'check_in_time' => now(),
                    'checked_in_by' => auth()->id(),
                ]);

                $message = 'Table reservation checked in successfully';
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'type' => 'success',
                'data' => [
                    'checked_in_at' => now()->format('Y-m-d H:i:s'),
                    'checked_in_by' => auth()->user()->full_name,
                ]
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    public function todayStats()
    {
        $today = now()->format('Y-m-d');
        
        $stats = [
            'total_tickets_checked_in' => EventTicket::whereDate('check_in_time', $today)->count(),
            'total_tables_checked_in' => EventTableReservation::whereDate('check_in_time', $today)->count(),
            'pending_tickets' => EventTicket::whereHas('event', function ($query) {
                    $query->whereDate('start_datetime', '>=', now()->subDay())
                          ->whereDate('end_datetime', '<=', now()->addDay());
                })
                ->where('check_in_status', false)
                ->count(),
            'pending_tables' => EventTableReservation::whereHas('event', function ($query) {
                    $query->whereDate('start_datetime', '>=', now()->subDay())
                          ->whereDate('end_datetime', '<=', now()->addDay());
                })
                ->where('check_in_status', false)
                ->count(),
        ];

        return response()->json($stats);
    }
}