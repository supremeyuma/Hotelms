<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Models\EventTicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

class EventService
{
    public function createTicketTypes(Event $event, array $ticketTypes): void
    {
        DB::transaction(function () use ($event, $ticketTypes) {
            foreach ($ticketTypes as $typeData) {
                EventTicketType::create(array_merge($typeData, [
                    'event_id' => $event->id,
                ]));
            }
        });
    }

    public function purchaseTicket(Event $event, array $data): EventTicket
    {
        $ticketType = EventTicketType::findOrFail($data['ticket_type_id']);
        
        if (!$ticketType->is_on_sale) {
            throw new \Exception('This ticket type is not currently available');
        }

        // Check event capacity limits
        $existingBookedCount = EventTicket::where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        if (($existingBookedCount + $data['quantity']) > $ticketType->max_per_person) {
            throw new \Exception('Exceeds event maximum tickets per person limit');
        }

        if (($existingBookedCount + $data['quantity']) > $event->max_tickets_per_person) {
            throw new \Exception('Exceeds event maximum tickets per person limit');
        }

        // Calculate remaining quantity
        $remainingQuantity = $ticketType->available_quantity - $data['quantity'];

        // Generate unique QR code
        $qrCode = $this->generateQRCode();

        // Create ticket
        $ticket = EventTicket::create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'guest_name' => $data['guest_name'],
            'guest_email' => $data['guest_email'],
            'guest_phone' => $data['guest_phone'] ?? null,
            'quantity' => $data['quantity'],
            'amount_paid' => $totalAmount,
            'payment_method' => $data['payment_method'] ?? 'online',
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_status' => 'pending',
            'status' => 'pending',
            'qr_code' => $qrCode,
        ]);

        // Update sold count
        $ticketType->incrementSoldCount($data['quantity']);

        return $ticket;
    }
    {
        return DB::transaction(function () use ($event, $data) {
            // Validate ticket availability
            $ticketType = EventTicketType::findOrFail($data['ticket_type_id']);
            
            if (!$ticketType->is_on_sale) {
                throw new \Exception('This ticket type is not currently available');
            }

            if ($ticketType->available_quantity < $data['quantity']) {
                throw new \Exception('Not enough tickets available');
            }

            if ($ticketType->max_per_person < $data['quantity']) {
                throw new \Exception('Exceeds maximum tickets per person');
            }

            // Check per-person limit for the event
            $existingTickets = EventTicket::where('event_id', $event->id)
                ->where('guest_email', $data['guest_email'])
                ->where('status', '!=', 'cancelled')
                ->sum('quantity');

            if (($existingTickets + $data['quantity']) > $event->max_tickets_per_person) {
                throw new \Exception('Exceeds event maximum tickets per person');
            }

            // Generate unique QR code
            $qrCode = $this->generateQRCode();

            // Calculate amount
            $totalAmount = $ticketType->price * $data['quantity'];

            // Create ticket
            $ticket = EventTicket::create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'quantity' => $data['quantity'],
                'amount_paid' => $totalAmount,
                'payment_method' => $data['payment_method'] ?? 'online',
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_status' => 'pending',
                'status' => 'pending',
                'qr_code' => $qrCode,
                'notes' => $data['notes'] ?? null,
            ]);

            // Update sold quantity
            $ticketType->incrementSoldCount($data['quantity']);

            return $ticket;
        });
    }

    public function reserveTable(Event $event, array $data): EventTableReservation
    {
        return DB::transaction(function () use ($event, $data) {
            if (!$event->has_table_reservations) {
                throw new \Exception('This event does not support table reservations');
            }

            // Check table availability (simplified - in real app, you'd check specific table availability)
            $existingReservations = EventTableReservation::where('event_id', $event->id)
                ->where('status', '!=', 'cancelled')
                ->sum('number_of_guests');

            $totalGuests = $existingReservations + $data['number_of_guests'];
            
            if ($totalGuests > ($event->table_capacity ?? 100)) {
                throw new \Exception('Not enough table capacity available');
            }

            // Generate unique QR code
            $qrCode = $this->generateQRCode();

            // Calculate amount
            $totalAmount = $event->table_price * $data['number_of_guests'];

            // Create reservation
            $reservation = EventTableReservation::create([
                'event_id' => $event->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'table_number' => $data['table_number'] ?? 'TBD',
                'number_of_guests' => $data['number_of_guests'],
                'amount_paid' => $totalAmount,
                'payment_method' => $data['payment_method'] ?? 'online',
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_status' => 'pending',
                'status' => 'pending',
                'qr_code' => $qrCode,
                'special_requests' => $data['special_requests'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            return $reservation;
        });
    }

    public function confirmPayment(string $reference, string $method, string $status): void
    {
        DB::transaction(function () use ($reference, $method, $status) {
            // Find ticket
            $ticket = EventTicket::where('payment_reference', $reference)
                ->orWhere('qr_code', $reference)
                ->first();

            if ($ticket && $ticket->payment_status === 'pending') {
                $ticket->update([
                    'payment_method' => $method,
                    'payment_status' => $status,
                    'status' => $status === 'paid' ? 'confirmed' : 'pending',
                ]);
            }

            // Find table reservation
            $reservation = EventTableReservation::where('payment_reference', $reference)
                ->orWhere('qr_code', $reference)
                ->first();

            if ($reservation && $reservation->payment_status === 'pending') {
                $reservation->update([
                    'payment_method' => $method,
                    'payment_status' => $status,
                    'status' => $status === 'paid' ? 'confirmed' : 'pending',
                ]);
            }
        });
    }

    public function checkInTicket(string $qrCode): EventTicket
    {
        $ticket = EventTicket::where('qr_code', $qrCode)->first();
        
        if (!$ticket) {
            throw new \Exception('Invalid ticket QR code');
        }

        if (!$ticket->can_be_checked_in) {
            throw new \Exception('Ticket cannot be checked in');
        }

        $ticket->update([
            'checked_in_at' => now(),
        ]);

        return $ticket;
    }

    public function checkInTableReservation(string $qrCode): EventTableReservation
    {
        $reservation = EventTableReservation::where('qr_code', $qrCode)->first();
        
        if (!$reservation) {
            throw new \Exception('Invalid reservation QR code');
        }

        if (!$reservation->can_be_checked_in) {
            throw new \Exception('Reservation cannot be checked in');
        }

        $reservation->update([
            'checked_in_at' => now(),
        ]);

        return $reservation;
    }

    public function refundTicket(int $ticketId, string $reason): EventTicket
    {
        return DB::transaction(function () use ($ticketId, $reason) {
            $ticket = EventTicket::findOrFail($ticketId);

            if (!$ticket->is_refundable) {
                throw new \Exception('Ticket is not refundable');
            }

            // Update ticket status
            $ticket->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'notes' => ($ticket->notes ?? '') . "\nRefunded: $reason",
            ]);

            // Restore ticket quantity
            if ($ticket->ticket_type_id) {
                $ticket->ticket_type->decrement('quantity_sold', $ticket->quantity);
            }

            // Mock Flutterwave refund processing
            $this->processRefund($ticket->payment_reference, $ticket->amount_paid, $reason);

            return $ticket;
        });
    }

    public function getEventStatistics(Event $event): array
    {
        $totalTicketsSold = $event->total_tickets_sold;
        $totalTicketRevenue = $event->total_tickets_revenue;
        $totalTablesReserved = $event->total_tables_reserved;
        $totalTableRevenue = $event->tableReservations()
            ->where('status', 'confirmed')
            ->sum('amount_paid');

        $ticketTypes = $event->ticketTypes()->withCount(['tickets' => function ($query) {
            $query->where('status', 'confirmed');
        }])->get();

        return [
            'total_tickets_sold' => $totalTicketsSold,
            'total_ticket_revenue' => $totalTicketRevenue,
            'total_tables_reserved' => $totalTablesReserved,
            'total_table_revenue' => $totalTableRevenue,
            'total_revenue' => $totalTicketRevenue + $totalTableRevenue,
            'ticket_types' => $ticketTypes->map(function ($type) {
                return [
                    'name' => $type->name,
                    'total_sold' => $type->tickets_count,
                    'revenue' => $type->tickets()->confirmed()->sum('amount_paid'),
                    'remaining' => $type->available_quantity,
                ];
            }),
        ];
    }

    public function generateEventQRCode(Event $event): string
    {
        $eventUrl = route('events.show', ['id' => $event->id]);
        return $this->generateQRCodeForUrl($eventUrl);
    }

    public function featureEvent(int $eventId): Event
    {
        $event = Event::findOrFail($eventId);
        $event->update(['is_featured' => true]);
        
        return $event;
    }

    public function unfeatureEvent(int $eventId): Event
    {
        $event = Event::findOrFail($eventId);
        $event->update(['is_featured' => false]);
        
        return $event;
    }

    protected function generateQRCode(): string
    {
        return 'EVT-' . strtoupper(Str::random(8)) . '-' . time();
    }

    protected function generateQRCodeForUrl(string $url): string
    {
        $qrCode = new Writer($url);
        $renderer = new Png();
        $qrCodeData = $qrCode->render($renderer);
        
        $fileName = 'qr-codes/' . Str::random(10) . '.png';
        
        Storage::disk('public')->put($fileName, $qrCodeData);
        
        return Storage::url($fileName);
    }

    protected function processRefund(string $reference, float $amount, string $reason): void
    {
        // Mock Flutterwave refund processing
        // In real implementation, you would integrate with Flutterwave API here
        logger()->info("Processing refund for reference: $reference, amount: $amount, reason: $reason");
        
        // Simulate API call delay
        usleep(500000); // 0.5 second delay
    }

    public function getAvailableEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::with(['ticketTypes' => function ($query) {
                $query->where('is_active', true)->orderBy('price');
            }])
            ->active()
            ->upcoming()
            ->orderBy('event_date')
            ->get();
    }

    public function getFeaturedEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::with(['ticketTypes', 'promotionalMedia'])
            ->active()
            ->featured()
            ->upcoming()
            ->orderBy('event_date')
            ->take(6)
            ->get();
    }
}