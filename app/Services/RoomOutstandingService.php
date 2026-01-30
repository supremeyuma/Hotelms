<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class RoomOutstandingService
{
    /**
     * Outstanding balance for a specific room under a booking
     */
    public function outstandingForRoom(Booking $booking, Room $room): float
    {
        $arDebits = $this->arAmount(
            bookingId: $booking->id,
            roomId: $room->id,
            column: 'debit'
        );

        $arCredits = $this->arAmount(
            bookingId: $booking->id,
            roomId: $room->id,
            column: 'credit'
        );

        return max($arDebits - $arCredits, 0);
    }

    /**
     * Outstanding balance for entire booking (all rooms)
     */
    public function outstandingForBooking(Booking $booking): float
    {
        $debits = $this->arAmount($booking->id, null, 'debit');
        $credits = $this->arAmount($booking->id, null, 'credit');

        return max($debits - $credits, 0);
    }

    protected function arAmount(
        int $bookingId,
        ?int $roomId,
        string $column
    ): float {
        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.name', 'Accounts Receivable')
            ->where('journal_entries.reference_id', $bookingId);

        if ($roomId !== null) {
            $query->where(function ($q) use ($roomId) {
                $q->where('journal_entries.description', 'like', "%Room {$roomId}%")
                  ->orWhere('journal_entries.reference_type', 'like', "%room%");
            });
        }

        return (float) $query->sum("journal_lines.$column");
    }
}
