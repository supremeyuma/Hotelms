<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DailyRevenueService
{
    public function generate(Carbon $date): array
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', 'revenue')
            ->whereDate('journal_entries.entry_date', $date)
            ->groupBy('accounts.name')
            ->select(
                'accounts.name as department',
                DB::raw('SUM(journal_lines.credit) as amount')
            )
            ->get()
            ->map(fn ($r) => [
                'department' => $r->department,
                'amount' => round($r->amount, 2),
            ])
            ->values()
            ->toArray();
    }
}
