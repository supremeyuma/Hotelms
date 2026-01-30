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

    /* ---------------------------------
       CHARGES (UI / OPERATIONAL ONLY)
    --------------------------------- */

    public function addCharge(
        Booking $booking,
        int $roomId,
        string $description,
        float $amount
    ): Charge {
        $charge = Charge::create([
            'booking_id' => $booking->id,
            'room_id'    => $roomId,
            'description'=> $description,
            'amount'     => $amount,
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
}
