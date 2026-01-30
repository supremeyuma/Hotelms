<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AccountsReceivableService
{
    /**
     * Outstanding AR for a booking (all rooms)
     */
    public function outstandingForBooking(int $bookingId): float
    {
        return $this->arBalance(
            fn ($q) => $q->where('journal_entries.reference_id', $bookingId)
        );
    }

    /**
     * Outstanding AR for a specific room under a booking
     */
    public function outstandingForRoom(int $bookingId, int $roomId): float
    {
        return $this->arBalance(
            fn ($q) => $q
                ->where('journal_entries.reference_id', $bookingId)
                ->where('journal_entries.description', 'LIKE', "%Room {$roomId}%")
        );
    }

    /**
     * Core AR balance calculator
     */
    protected function arBalance(callable $scope): float
    {
        $query = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.name', 'Accounts Receivable');

        $scope($query);

        $debits = (clone $query)->sum('journal_lines.debit');
        $credits = (clone $query)->sum('journal_lines.credit');

        return round(max($debits - $credits, 0), 2);
    }
}
