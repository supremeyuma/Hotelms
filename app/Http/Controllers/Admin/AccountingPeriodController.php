<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\Accounting\PeriodService;
use App\Services\Accounting\TaxService;

class AccountingPeriodController extends Controller
{
    public function __construct(
        protected PeriodService $periodService,
        protected TaxService $taxService
    ) {}

    public function index()
    {
        return Inertia::render('Admin/AccountingPeriods', [
            'periods' => $this->periodService->getAllPeriods(),
            'openPeriods' => $this->periodService->getOpenPeriods(),
            'closedPeriods' => $this->periodService->getClosedPeriods(),
            'currentPeriod' => $this->periodService->getCurrentPeriod(),
            'nextPeriodDate' => $this->periodService->getNextPeriodDate()?->toDateString(),
            'routePrefix' => 'finance',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $period = $this->periodService->createPeriod(
                new \Carbon\Carbon($validated['start_date']),
                new \Carbon\Carbon($validated['end_date'])
            );

            return back()->with('success', "Accounting period created successfully.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function close(Request $request, int $periodId)
    {
        try {
            $this->periodService->closePeriod($periodId, auth()->id());
            return back()->with('success', "Accounting period closed successfully.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reopen(Request $request, int $periodId)
    {
        if (!auth()->user()->hasAnyRole(['accountant', 'Accountant', 'md'])) {
            return back()->with('error', 'Only accountants or the managing director can reopen accounting periods.');
        }

        try {
            $this->periodService->reopenPeriod($periodId, auth()->id());
            return back()->with('success', "Accounting period reopened successfully.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(int $periodId)
    {
        $period = \App\Models\AccountingPeriod::findOrFail($periodId);
        $journalEntries = \App\Models\JournalEntry::with('lines.account')
            ->whereBetween('entry_date', [$period->start_date, $period->end_date])
            ->orderBy('entry_date', 'desc')
            ->get();
        
        return Inertia::render('Admin/AccountingPeriodShow', [
            'period' => $period,
            'journalEntries' => $journalEntries,
            'routePrefix' => 'finance',
        ]);
    }

    public function initializeTaxAccounts(Request $request)
    {
        try {
            $this->taxService->createDefaultTaxAccounts();
            return back()->with('success', "Default tax accounts initialized successfully.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function checkPeriodStatus(Request $request)
    {
        $date = new \Carbon\Carbon($request->input('date'));
        
        return response()->json([
            'status' => $this->periodService->getPeriodStatus($date),
            'is_open' => $this->periodService->canCreateJournalEntry($date),
            'period' => $this->periodService->getPeriodForDate($date),
        ]);
    }

    public function autoCloseExpired()
    {
        try {
            $closedCount = $this->periodService->autoCloseExpiredPeriods();
            return back()->with('success', "Auto-closed {$closedCount} expired periods.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
