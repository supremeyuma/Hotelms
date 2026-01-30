<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class RoomBalanceService
{
    /**
     * Can a room checkout? (AR == 0 for this room)
     */
    public function roomCanCheckout(Booking $booking, Room $room): bool
    {
        return $this->outstandingForRoom($booking, $room) <= 0;
    }

    /**
     * Outstanding balance for a room (GL-based)
     */
    public function outstandingForRoom(Booking $booking, Room $room): float
    {
        $debits = $this->arAmount(
            bookingId: $booking->id,
            roomId: $room->id,
            column: 'debit'
        );

        $credits = $this->arAmount(
            bookingId: $booking->id,
            roomId: $room->id,
            column: 'credit'
        );

        return max($debits - $credits, 0);
    }

    /**
     * Sum AR debits or credits for a room
     */
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
