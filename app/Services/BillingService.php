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
        $booking->loadMissing(['rooms.roomType', 'charges.room.roomType', 'payments.room.roomType']);

        $charges = $booking->charges
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
            ->all();

        $payments = $booking->payments
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

        $totalCharges = max(
            (float) ($booking->total_amount ?? 0) + collect($charges)->sum('amount'),
            0
        );
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
        $debits = $this->arAmount($booking->id, $roomId, 'debit');
        $credits = $this->arAmount($booking->id, $roomId, 'credit');

        return max($debits - $credits, 0);
    }

    protected function arAmount(
        int $bookingId,
        int $roomId,
        string $column
    ): float {
        return (float) DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.name', 'Accounts Receivable')
            ->where('journal_entries.reference_id', $bookingId)
            ->where(function ($q) use ($roomId) {
                $q->where('journal_entries.description', 'like', "%Room {$roomId}%")
                  ->orWhere('journal_entries.reference_type', 'like', '%room%');
            })
            ->sum("journal_lines.$column");
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
}
