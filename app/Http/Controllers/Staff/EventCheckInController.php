<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EventCheckInController extends Controller
{
    /* =========================================================
     | Dashboard
     ========================================================= */
    public function index()
    {
        $activeEvents = Event::where('is_active', true)
            ->where('start_datetime', '<=', now())
            ->where('end_datetime', '>=', now()->subHours(4))
            ->orderByDesc('start_datetime')
            ->get(['id', 'title', 'start_datetime', 'end_datetime', 'venue']);

        return Inertia::render('Staff/Events/CheckIn', [
            'activeEvents' => $activeEvents,
        ]);
    }

    /* =========================================================
     | QR VALIDATION (READ-ONLY)
     ========================================================= */
    public function validateQrCode(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $parsed = $this->parseQrCode($request->qr_code);

        if (!$parsed) {
            return $this->invalidResponse();
        }

        return match ($parsed['type']) {
            'ticket' => $this->validateTicket($parsed['code']),
            'table'  => $this->validateTableReservation($parsed['code']),
            default  => $this->invalidResponse(),
        };
    }

    /* =========================================================
     | CHECK-IN (STATE CHANGE)
     ========================================================= */
    public function checkIn(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $parsed = $this->parseQrCode($request->qr_code);

        if (!$parsed) {
            return $this->invalidResponse();
        }

        try {
            DB::beginTransaction();

            if ($parsed['type'] === 'ticket') {
                $record = EventTicket::where('qr_code', $parsed['code'])->lockForUpdate()->first();
            } else {
                $record = EventTableReservation::where('qr_code', $parsed['code'])->lockForUpdate()->first();
            }

            if (!$record || $record->checked_in_at) {
                throw new \Exception('Failed to validate QR code');
            }

            $event = $record->event;

            // Enforce time window again (anti-race)
            if ($event->start_datetime > now()->addHours(2)) {
                throw new \Exception('Event has not started yet');
            }

            if ($event->end_datetime < now()->subHours(4)) {
                throw new \Exception('Event has ended');
            }

            $record->update([
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'type' => 'success',
                'message' => $parsed['type'] === 'ticket'
                    ? 'Ticket checked in successfully'
                    : 'Table reservation checked in successfully',
                'data' => [
                    'checked_in_at' => now()->format('Y-m-d H:i:s'),
                    'checked_in_by' => auth()->user()?->name ?? 'Staff',
                ]
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();

            logger()->warning('QR check-in failed', [
                'error' => $e->getMessage(),
                'qr' => $request->qr_code,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'type' => 'error',
                'message' => 'Failed to validate QR code',
            ]);
        }
    }

    /* =========================================================
     | TODAY STATS
     ========================================================= */
    public function todayStats()
    {
        $today = now()->toDateString();

        return response()->json([
            'total_tickets_checked_in' =>
                EventTicket::whereDate('checked_in_at', $today)->count(),

            'total_tables_checked_in' =>
                EventTableReservation::whereDate('checked_in_at', $today)->count(),

            'pending_tickets' =>
                EventTicket::whereNull('checked_in_at')
                    ->whereHas('event', fn ($q) =>
                        $q->where('start_datetime', '<=', now())
                          ->where('end_datetime', '>=', now()->subHours(4))
                    )->count(),

            'pending_tables' =>
                EventTableReservation::whereNull('checked_in_at')
                    ->whereHas('event', fn ($q) =>
                        $q->where('start_datetime', '<=', now())
                          ->where('end_datetime', '>=', now()->subHours(4))
                    )->count(),
        ]);
    }

    /* =========================================================
     | VALIDATORS
     ========================================================= */
    private function validateTicket(string $code)
    {
        $ticket = EventTicket::with('event')
            ->where('qr_code', $code)
            ->first();

        if (!$ticket) {
            return $this->invalidResponse();
        }

        if ($ticket->checked_in_at) {
            return $this->alreadyUsedResponse($ticket->checked_in_at);
        }

        return $this->validResponse([
            'id' => $ticket->id,
            'event_title' => $ticket->event->title,
            'ticket_type' => optional($ticket->ticketType)->name,
            'guest_name' => $ticket->guest_name ?? 'Guest',
            'guest_email' => $ticket->guest_email ?? '',
            'quantity' => $ticket->quantity,
            'total_amount' => $ticket->amount,
            'event_venue' => $ticket->event->venue,
            'event_datetime' => $ticket->event->start_datetime,
        ]);
    }

    private function validateTableReservation(string $code)
    {
        $reservation = EventTableReservation::with('event')
            ->where('qr_code', $code)
            ->first();

        if (!$reservation) {
            return $this->invalidResponse();
        }

        if ($reservation->checked_in_at) {
            return $this->alreadyUsedResponse($reservation->checked_in_at);
        }

        return $this->validResponse([
            'id' => $reservation->id,
            'event_title' => $reservation->event->title,
            'guest_name' => $reservation->guest_name ?? 'Guest',
            'guest_email' => $reservation->guest_email ?? '',
            'table_number' => $reservation->table_number,
            'guest_count' => $reservation->number_of_guests,
            'total_amount' => $reservation->amount,
            'special_requests' => $reservation->special_requests,
            'event_venue' => $reservation->event->venue,
            'event_datetime' => $reservation->event->start_datetime,
        ]);
    }

    /* =========================================================
     | HELPERS
     ========================================================= */
    private function parseQrCode(string $raw): ?array
    {
        $parts = parse_url($raw);
        parse_str($parts['query'] ?? '', $query);

        if (isset($query['ticket'])) {
            return ['type' => 'ticket', 'code' => $query['ticket']];
        }

        if (isset($query['reservation'])) {
            return ['type' => 'table', 'code' => $query['reservation']];
        }

        return null;
    }

    private function invalidResponse()
    {
        return response()->json([
            'success' => false,
            'type' => 'error',
            'message' => 'Failed to validate QR code',
        ]);
    }

    private function alreadyUsedResponse($time)
    {
        return response()->json([
            'success' => false,
            'type' => 'warning',
            'message' => 'Already checked in',
            'data' => [
                'checked_in_at' => $time,
            ],
        ]);
    }

    private function validResponse(array $data)
    {
        return response()->json([
            'success' => true,
            'type' => 'ready',
            'message' => 'Valid QR code',
            'data' => $data,
        ]);
    }
}
