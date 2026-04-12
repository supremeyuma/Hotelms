<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Events\BillingUpdated;
use App\Services\PaymentAccountingService;

class BillingService
{
    public function __construct(
        protected PaymentAccountingService $paymentAccounting,
    ) {}

    public function getBillingHistory(Booking $booking): array
    {
        $booking->loadMissing(['room.roomType', 'roomType', 'rooms.roomType', 'charges.room.roomType', 'payments.room.roomType']);

        $persistedCharges = $booking->charges
            ->sortByDesc('created_at')
            ->values()
            ->map(function (Charge $charge) {
                return [
                    'id' => $charge->id,
                    'room_id' => $charge->room_id,
                    'room_label' => $this->roomLabel($charge->room),
                    'description' => $charge->description,
                    'amount' => (float) $charge->amount,
                    'created_at' => optional($charge->created_at)?->toIso8601String(),
                ];
            })
            ->values();

        $baseBookingAmount = $this->effectiveBookingAmount($booking);
        $baseCharge = collect();

        if ($baseBookingAmount > 0) {
            $baseCharge = collect([[
                'id' => 'booking-base-' . $booking->id,
                'room_id' => $booking->room_id,
                'room_label' => $this->bookingRoomLabel($booking),
                'description' => 'Accommodation',
                'amount' => $baseBookingAmount,
                'created_at' => optional($booking->created_at)?->toIso8601String(),
            ]]);
        }

        $charges = $baseCharge
            ->concat($persistedCharges)
            ->all();

        $payments = $booking->payments
            ->filter(fn (Payment $payment) => in_array(strtolower((string) $payment->status), ['successful', 'completed', 'paid'], true))
            ->sortByDesc('created_at')
            ->values()
            ->map(function (Payment $payment) {
                return [
                    'id' => $payment->id,
                    'room_id' => $payment->room_id,
                    'room_label' => $this->roomLabel($payment->room),
                    'method' => $payment->method,
                    'notes' => $payment->notes,
                    'reference' => $payment->reference,
                    'amount' => (float) ($payment->amount_paid ?? $payment->amount),
                    'created_at' => optional($payment->created_at)?->toIso8601String(),
                ];
            })
            ->all();

        $totalCharges = max((float) collect($charges)->sum('amount'), 0);
        $totalPayments = collect($payments)->sum('amount');

        return [
            'charges' => $charges,
            'payments' => $payments,
            'total_charges' => $totalCharges,
            'total_payments' => $totalPayments,
            'outstanding' => max($totalCharges - $totalPayments, 0),
            'assigned_room_options' => $booking->rooms->map(fn ($room) => [
                'id' => $room->id,
                'label' => $this->roomLabel($room),
            ])->values()->all(),
            'has_multiple_rooms' => $booking->rooms->count() > 1,
        ];
    }

    /* ---------------------------------
       CHARGES (UI / OPERATIONAL ONLY)
    --------------------------------- */

    public function addCharge(
        Booking $booking,
        int $roomId,
        string $description,
        float $amount,
        string $type = 'manual'
    ): Charge {
        $charge = Charge::create([
            'booking_id' => $booking->id,
            'room_id'    => $roomId,
            'description'=> $description,
            'amount'     => $amount,
            'type'       => $type,
        ]);

        event(new BillingUpdated($booking));

        return $charge;
    }

    /* ---------------------------------
       PAYMENTS (AUTHORITATIVE → GL)
    --------------------------------- */

    public function addPayment(
        Booking $booking,
        int $roomId,
        float $amount,
        string $method,
        ?string $reference = null,
        ?string $notes = null
    ): Payment {
        return DB::transaction(function () use (
            $booking,
            $roomId,
            $amount,
            $method,
            $reference,
            $notes
        ) {
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'room_id'    => $roomId,
                'amount'     => $amount,
                'method'     => $method,
                'reference'  => $reference,
                'status'     => 'successful',
                'notes'      => $notes,
                'paid_at'    => now(),
            ]);

            // 🔑 ACCOUNTING (AR → CASH/BANK)
            $this->paymentAccounting->handleSuccessful($payment);

            event(new BillingUpdated($booking));

            return $payment;
        });
    }

    /* ---------------------------------
       OUTSTANDING (GL-DRIVEN, ROOM-LEVEL)
    --------------------------------- */

    public function outstandingForRoom(Booking $booking, int $roomId): float
    {
        $charges = (float) Charge::query()
            ->where('booking_id', $booking->id)
            ->where('room_id', $roomId)
            ->sum('amount');

        $payments = (float) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('room_id', $roomId)
            ->whereIn('status', ['successful', 'completed'])
            ->sum(DB::raw('COALESCE(amount_paid, amount)'));

        return round(max($charges - $payments, 0), 2);
    }

    protected function roomLabel($room): string
    {
        if (! $room) {
            return 'Unassigned room';
        }

        return trim(collect([
            $room->roomType?->title,
            $room->name ?: $room->room_number,
        ])->filter()->implode(' - '));
    }

    protected function bookingRoomLabel(Booking $booking): string
    {
        $labels = $booking->rooms
            ->map(fn ($room) => $this->roomLabel($room))
            ->filter()
            ->values();

        if ($labels->isNotEmpty()) {
            return $labels->implode(', ');
        }

        return $this->roomLabel($booking->room);
    }

    protected function effectiveBookingAmount(Booking $booking): float
    {
        $details = is_array($booking->details) ? $booking->details : [];
        $override = is_array($details['price_override'] ?? null) ? $details['price_override'] : null;
        $discount = is_array($details['discount'] ?? null) ? $details['discount'] : null;
        $discountPricing = is_array($discount['pricing'] ?? null) ? $discount['pricing'] : null;

        $overrideAmount = isset($override['override_amount'])
            ? round((float) $override['override_amount'], 2)
            : null;

        if ($overrideAmount !== null) {
            return $overrideAmount;
        }

        $discountedTotal = isset($discountPricing['total'])
            ? round((float) $discountPricing['total'], 2)
            : null;

        if ($discountedTotal !== null) {
            return $discountedTotal;
        }

        $storedTotal = $booking->total_amount !== null
            ? round((float) $booking->total_amount, 2)
            : null;

        if ($storedTotal !== null) {
            return $storedTotal;
        }

        $nightlyRate = (float) ($booking->nightly_rate ?: $booking->roomType?->base_price ?: $booking->room?->roomType?->base_price ?: 0);
        $roomCount = max((int) ($booking->quantity ?: $booking->rooms->count() ?: 1), 1);
        $nights = $booking->check_in && $booking->check_out
            ? max($booking->check_in->diffInDays($booking->check_out), 1)
            : 1;

        return round($nightlyRate * $roomCount * $nights, 2);
    }
}
