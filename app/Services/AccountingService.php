<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountingService
{
    public function postEntry(
        string $referenceType,
        int $referenceId,
        string $description,
        array $lines,
        ?int $userId = null,
        ?Carbon $date = null
    ): JournalEntry {
        app(\App\Services\Accounting\PeriodService::class)
            ->assertOpen($date ?? now());
            
        return DB::transaction(function () use (
            $referenceType,
            $referenceId,
            $description,
            $lines,
            $userId,
            $date
        ) {
            $totalDebit = collect($lines)->sum('debit');
            $totalCredit = collect($lines)->sum('credit');

            if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
                throw new \RuntimeException('Journal entry is not balanced');
            }

            $entry = JournalEntry::create([
                'entry_date'     => $date ?? now(),
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'description'    => $description,
                'created_by'     => $userId,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }

            return $entry;
        });
    }
}
