<?php

namespace App\Services;

use App\Models\Booking;
use App\Services\Ledgers\RoomNightRevenueLedger;
use Illuminate\Support\Carbon;

class BookingAccountingService
{
    public function __construct(
        protected RoomNightRevenueLedger $ledger
    ) {}

    /**
     * Called when rooms are checked in
     */
    public function handleRoomCheckIn(Booking $booking): void
    {
        $booking->load(['rooms.roomType']);

        foreach ($booking->rooms as $room) {
            $this->ledger->postRoomCheckIn($booking, $room);
        }
    }

    /**
     * Night audit
     */
    public function handleNightAudit(Booking $booking, Carbon $date): void
    {
        $booking->load(['rooms.roomType']);

        foreach ($booking->rooms as $room) {
            $this->ledger->recognizeNight($booking, $room, $date);
        }
    }

    /**
     * Checkout safeguard
     */
    public function handleCheckout(Booking $booking): void
    {
        $booking->load(['rooms.roomType']);

        foreach ($booking->rooms as $room) {
            $remaining = $this->remainingRoomBalance($booking, $room);
            $this->ledger->recognizeRemaining($booking, $room, $remaining);
        }
    }

    protected function remainingRoomBalance(Booking $booking, $room): float
    {
        $total = $room->roomType->rate * $booking->nights;

        $recognized = \DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.reference_id', $booking->id)
            ->where('accounts.name', 'Room Revenue')
            ->sum('journal_lines.credit');

        return max($total - $recognized, 0);
    }
}
