<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BalanceSheetService
{
    public function generate(Carbon $asOf): array
    {
        return [
            'as_of' => $asOf->toDateString(),
            'assets' => $this->byType('asset', $asOf),
            'liabilities' => $this->byType('liability', $asOf),
            'equity' => $this->byType('equity', $asOf),
            'totals' => $this->totals($asOf),
        ];
    }

    protected function byType(string $type, Carbon $asOf): array
    {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', $type)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->groupBy('accounts.id', 'accounts.name')
            ->select(
                'accounts.name',
                DB::raw('SUM(journal_lines.debit - journal_lines.credit) as balance')
            )
            ->get()
            ->map(fn ($r) => [
                'account' => $r->name,
                'balance' => round($r->balance, 2),
            ])
            ->values()
            ->toArray();
    }

    protected function totals(Carbon $asOf): array
    {
        $assets = $this->sum('asset', $asOf);
        $liabilities = $this->sum('liability', $asOf);
        $equity = $this->sum('equity', $asOf);

        return [
            'assets' => $assets,
            'liabilities_equity' => round($liabilities + $equity, 2),
        ];
    }

    protected function sum(string $type, Carbon $asOf): float
    {
        return (float) DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', $type)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->sum(DB::raw('journal_lines.debit - journal_lines.credit'));
    }
}
