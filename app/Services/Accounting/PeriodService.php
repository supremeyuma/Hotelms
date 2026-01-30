<?php

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PeriodService
{
    public function close(Carbon $start, Carbon $end): AccountingPeriod
    {
        if (AccountingPeriod::whereBetween('start_date', [$start, $end])
            ->where('is_closed', true)->exists()) {

            throw ValidationException::withMessages([
                'period' => 'Period already closed.'
            ]);
        }

        return AccountingPeriod::create([
            'start_date' => $start,
            'end_date' => $end,
            'is_closed' => true,
        ]);
    }

    public function assertOpen(Carbon $date): void
    {
        if (AccountingPeriod::where('is_closed', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists()) {

            throw ValidationException::withMessages([
                'period' => 'Accounting period is closed.'
            ]);
        }
    }

    public function getCurrentPeriod(): ?AccountingPeriod
    {
        return AccountingPeriod::where('is_closed', false)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    public function getPeriodForDate(Carbon $date): ?AccountingPeriod
    {
        return AccountingPeriod::where('is_closed', false)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    public function isOpen(Carbon $date): bool
    {
        try {
            $this->assertOpen($date);
            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    public function createPeriod(Carbon $startDate, Carbon $endDate): AccountingPeriod
    {
        $this->validatePeriodDates($startDate, $endDate);

        $overlapping = AccountingPeriod::where(function ($query) use ($startDate, $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                  ->where('end_date', '>=', $startDate);
            });
        })->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'period' => 'Accounting period overlaps with existing period'
            ]);
        }

        return AccountingPeriod::create([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_closed' => false,
        ]);
    }

    public function closePeriod(int $periodId, ?int $userId = null): AccountingPeriod
    {
        $period = AccountingPeriod::findOrFail($periodId);

        if ($period->is_closed) {
            throw ValidationException::withMessages([
                'period' => 'Accounting period is already closed'
            ]);
        }

        $period->update(['is_closed' => true]);

        // Log the closure
        if ($userId) {
            $period->notes = "Closed by user ID: {$userId} on " . now()->toDateTimeString();
            $period->save();
        }

        return $period;
    }

    public function reopenPeriod(int $periodId, ?int $userId = null): AccountingPeriod
    {
        $period = AccountingPeriod::findOrFail($periodId);

        if (!$period->is_closed) {
            throw ValidationException::withMessages([
                'period' => 'Accounting period is already open'
            ]);
        }

        $period->update(['is_closed' => false]);

        // Log the reopening
        if ($userId) {
            $period->notes = "Reopened by user ID: {$userId} on " . now()->toDateTimeString();
            $period->save();
        }

        return $period;
    }

    public function getAllPeriods(): \Illuminate\Database\Eloquent\Collection
    {
        return AccountingPeriod::orderBy('start_date', 'desc')->get();
    }

    public function getClosedPeriods(): \Illuminate\Database\Eloquent\Collection
    {
        return AccountingPeriod::where('is_closed', true)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getOpenPeriods(): \Illuminate\Database\Eloquent\Collection
    {
        return AccountingPeriod::where('is_closed', false)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function validatePeriodDates(Carbon $startDate, Carbon $endDate): void
    {
        if ($startDate->greaterThan($endDate)) {
            throw ValidationException::withMessages([
                'dates' => 'Start date must be before end date'
            ]);
        }

        if ($startDate->greaterThan(now())) {
            throw ValidationException::withMessages([
                'dates' => 'Start date cannot be in the future'
            ]);
        }
    }

    public function canCreateJournalEntry(Carbon $date): bool
    {
        try {
            $this->assertOpen($date);
            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    public function getPeriodStatus(Carbon $date): string
    {
        $period = $this->getPeriodForDate($date);

        if (!$period) {
            return 'no_period';
        }

        return $period->is_closed ? 'closed' : 'open';
    }

    public function getNextPeriodDate(): ?Carbon
    {
        $lastPeriod = AccountingPeriod::orderBy('end_date', 'desc')->first();

        if (!$lastPeriod) {
            return now()->startOfMonth();
        }

        return $lastPeriod->end_date->addDay();
    }

    public function autoCloseExpiredPeriods(): int
    {
        $expiredPeriods = AccountingPeriod::where('is_closed', false)
            ->where('end_date', '<', now()->subDays(7))
            ->get();

        $closedCount = 0;
        foreach ($expiredPeriods as $period) {
            $period->update(['is_closed' => true]);
            $closedCount++;
        }

        return $closedCount;
    }
}
