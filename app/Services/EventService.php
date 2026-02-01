<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTableReservation;
use App\Models\EventTicketType;
use App\Services\Accounting\TaxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\EventTicketPurchaseConfirmation;
use App\Mail\EventTableReservationConfirmation;

class EventService
{
    public function __construct(
        protected TaxService $taxService,
        protected PricingService $pricingService,
    ) {}

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
        return DB::transaction(function () use ($event, $data) {
            // Validate ticket availability
            $ticketType = EventTicketType::findOrFail($data['ticket_type_id']);
            
            if (!$ticketType->is_active) {
                throw new \Exception('This ticket type is no longer active');
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

            // Calculate base amount
            $baseAmount = $ticketType->price * $data['quantity'];

            // Calculate pricing with tax breakdown (1.5% VAT, 1% service charge for tickets)
            $pricing = $this->pricingService->calculatePricing($baseAmount, 0.015, 0.01);

            // Create ticket
            $ticket = EventTicket::create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'quantity' => $data['quantity'],
                'base_amount' => $pricing['base_amount'],
                'vat_amount' => $pricing['vat'],
                'service_charge_amount' => $pricing['service_charge'],
                'amount' => $pricing['total'],
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

            // Get table type
            $tableType = \App\Models\EventTableType::where('id', $data['table_type_id'])
                ->where('event_id', $event->id)
                ->first();

            if (!$tableType) {
                throw new \Exception('Invalid table type selected');
            }

            // Generate unique QR code
            $qrCode = $this->generateQRCode();

            // Calculate base amount (table price is fixed, not dependent on number of guests)
            $numberOfGuests = $data['number_of_guests'] ?? null;
            $baseAmount = $tableType->price;

            // Calculate pricing with tax breakdown (1.5% VAT, 1% service charge for table reservations)
            $pricing = $this->pricingService->calculatePricing($baseAmount, 0.015, 0.01);

            // Create reservation
            $reservation = EventTableReservation::create([
                'event_id' => $event->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'table_number' => $tableType->name,
                'number_of_guests' => $numberOfGuests,
                'base_amount' => $pricing['base_amount'],
                'vat_amount' => $pricing['vat'],
                'service_charge_amount' => $pricing['service_charge'],
                'amount' => $pricing['total'],
                'payment_method' => $data['payment_method'] ?? 'online',
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_status' => 'pending',
                'status' => 'pending',
                'qr_code' => $qrCode,
                'special_requests' => 'Table Type: ' . $tableType->name,
                'notes' => 'Price: ₦' . number_format($tableType->price, 2),
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

                // Post taxes to accounting system on successful payment
                if ($status === 'paid') {
                    $this->taxService->postAllTaxes(
                        $ticket->base_amount,
                        'EventTicket',
                        $ticket->id,
                        "Ticket: {$ticket->event->title}",
                        auth()?->id()
                    );
                    
                    $this->sendTicketConfirmationEmail($ticket);
                }
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

                // Post taxes to accounting system on successful payment
                if ($status === 'paid') {
                    $this->taxService->postAllTaxes(
                        $reservation->base_amount,
                        'EventTableReservation',
                        $reservation->id,
                        "Table Reservation: {$reservation->event->title}",
                        auth()?->id()
                    );
                    
                    $this->sendTableReservationConfirmationEmail($reservation);
                }
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
        $eventUrl = route('events.show', ['event' => $event->id]);
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
        try {
            // Create a simple QR code using data URI format
            $qrCodeData = "QR:$url";
            $fileName = 'qr-codes/' . Str::random(10) . '.txt';
            
            // Create a simple placeholder QR code content
            $content = "Event QR Code\nURL: $url\nReference: " . Str::random(8) . "\n\nPlease check in at the event with this reference.";
            
            Storage::disk('public')->put($fileName, $content);
            
            return Storage::url($fileName);
        } catch (\Exception $e) {
            // Fallback: create simple text-based QR code placeholder
            logger()->error('QR code generation failed: ' . $e->getMessage());
            
            $fileName = 'qr-codes/placeholder-' . Str::random(10) . '.txt';
            $content = "QR Code for: $url\n\n(This is a placeholder - QR code generation service temporarily unavailable)";
            
            Storage::disk('public')->put($fileName, $content);
            
            return Storage::url($fileName);
        }
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
            ->orderBy('start_datetime')
            ->get();
    }

    public function getFeaturedEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return Event::with(['ticketTypes', 'promotionalMedia'])
            ->active()
            ->featured()
            ->upcoming()
            ->orderBy('start_datetime')
            ->take(6)
            ->get();
    }

    protected function sendTicketConfirmationEmail(EventTicket $ticket): void
    {
        try {
            $event = $ticket->event;
            $qrCodeUrl = $this->generateTicketQRCode($ticket);
            
            Mail::to($ticket->guest_email)
                ->cc(config('mail.from.address'))
                ->send(new EventTicketPurchaseConfirmation($ticket, $event, $qrCodeUrl));
                
            logger()->info("Ticket confirmation email sent to: {$ticket->guest_email}");
        } catch (\Exception $e) {
            logger()->error("Failed to send ticket confirmation email: " . $e->getMessage());
        }
    }

    protected function sendTableReservationConfirmationEmail(EventTableReservation $reservation): void
    {
        try {
            $event = $reservation->event;
            $qrCodeUrl = $this->generateReservationQRCode($reservation);
            
            Mail::to($reservation->guest_email)
                ->cc(config('mail.from.address'))
                ->send(new EventTableReservationConfirmation($reservation, $event, $qrCodeUrl));
                
            logger()->info("Table reservation confirmation email sent to: {$reservation->guest_email}");
        } catch (\Exception $e) {
            logger()->error("Failed to send table reservation confirmation email: " . $e->getMessage());
        }
    }

    protected function generateTicketQRCode(EventTicket $ticket): string
    {
        $url = route('events.show', ['event' => $ticket->event_id]) . '?ticket=' . $ticket->qr_code;
        return $this->generateQRCodeForUrl($url);
    }

    protected function generateReservationQRCode(EventTableReservation $reservation): string
    {
        $url = route('events.show', ['event' => $reservation->event_id]) . '?reservation=' . $reservation->qr_code;
        return $this->generateQRCodeForUrl($url);
    }
}