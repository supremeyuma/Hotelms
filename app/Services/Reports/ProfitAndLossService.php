<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfitAndLossService
{
    public function generate(
        Carbon $from,
        Carbon $to
    ): array {
        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'revenue' => $this->byAccountType('revenue', $from, $to),
            'expenses' => $this->byAccountType('expense', $from, $to),
            'totals' => $this->totals($from, $to),
        ];
    }

    protected function byAccountType(
        string $type,
        Carbon $from,
        Carbon $to
    ): array {
        return DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', $type)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->groupBy('accounts.id', 'accounts.name')
            ->select(
                'accounts.name',
                DB::raw('SUM(journal_lines.credit - journal_lines.debit) as amount')
            )
            ->orderBy('accounts.name')
            ->get()
            ->map(fn ($row) => [
                'account' => $row->name,
                'amount'  => round($row->amount, 2),
            ])
            ->values()
            ->toArray();
    }

    protected function totals(Carbon $from, Carbon $to): array
    {
        $revenue = $this->sumType('revenue', $from, $to);
        $expenses = $this->sumType('expense', $from, $to);

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'net_profit' => round($revenue - $expenses, 2),
        ];
    }

    protected function sumType(
        string $type,
        Carbon $from,
        Carbon $to
    ): float {
        return (float) DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.type', $type)
            ->whereBetween('journal_entries.entry_date', [$from, $to])
            ->sum(DB::raw('journal_lines.credit - journal_lines.debit'));
    }
}
