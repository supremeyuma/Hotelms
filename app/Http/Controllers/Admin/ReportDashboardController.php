<?php
// app/Http/Controllers/Admin/ReportDashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\Payment;
use App\Services\Reports\OccupancyReportService;
use App\Services\Reports\StaffReportService;
use App\Services\Reports\InventoryReportService;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportDashboardController extends Controller
{
    public function operations(
        OccupancyReportService $occupancy,
        StaffReportService $staff,
        InventoryReportService $inventory
    ) {
        return Inertia::render('Admin/Reports/Dashboard', [
            'mode' => 'operations',
            'title' => 'Operations Reports',
            'kpis' => [
                'occupancy' => $occupancy->summary(),
                'staff'     => $staff->summary(),
                'inventory' => $inventory->summary(),
            ],
            'links' => [
                'primary' => route('admin.reports.occupancy'),
                'secondary' => route('admin.reports.staff'),
                'tertiary' => route('admin.reports.inventory'),
            ],
            'charts' => [
                [
                    'title' => 'Occupancy Trend',
                    'endpoint' => '/admin/reports/charts/occupancy',
                ],
                [
                    'title' => 'Inventory Movement',
                    'endpoint' => '/admin/reports/charts/inventory',
                ],
            ],
        ]);
    }

    public function finance(
    ) {
        $summaryFrom = Carbon::now()->subDays(29)->startOfDay();
        $today = Carbon::today();

        $chargeSummaryQuery = Charge::query()
            ->whereDate('charge_date', '>=', $summaryFrom->toDateString());

        $paymentSummaryQuery = $this->successfulPaymentsQuery()
            ->where(function ($query) use ($summaryFrom) {
                $query->whereDate('paid_at', '>=', $summaryFrom->toDateString())
                    ->orWhere(function ($nested) use ($summaryFrom) {
                        $nested->whereNull('paid_at')
                            ->whereDate('created_at', '>=', $summaryFrom->toDateString());
                    });
            });

        $activeBookings = Booking::query()
            ->with(['user:id,name,email', 'charges:id,booking_id,amount', 'payments:id,booking_id,amount,amount_paid,status'])
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get();

        $outstandingBookings = $activeBookings
            ->map(function (Booking $booking) {
                $chargeTotal = (float) $booking->charges->sum(fn (Charge $charge) => (float) $charge->amount);
                $paymentTotal = (float) $booking->payments
                    ->filter(fn (Payment $payment) => in_array($payment->status, ['successful', 'completed'], true))
                    ->sum(fn (Payment $payment) => (float) ($payment->amount_paid ?? $payment->amount ?? 0));

                return [
                    'booking_id' => $booking->id,
                    'outstanding' => round(max($chargeTotal - $paymentTotal, 0), 2),
                ];
            })
            ->filter(fn (array $booking) => $booking['outstanding'] > 0)
            ->values();

        $recentCharges = Charge::query()
            ->with(['booking:id,booking_code,guest_name,guest_email', 'room:id,name,room_number'])
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(function (Charge $charge) {
                return [
                    'id' => 'charge-' . $charge->id,
                    'kind' => 'charge',
                    'label' => $charge->description ?: 'Charge posted',
                    'booking_code' => $charge->booking?->booking_code,
                    'guest' => $charge->booking?->guest_name ?: $charge->booking?->guest_email ?: 'Guest',
                    'room' => $charge->room?->name ?: $charge->room?->room_number ?: 'Unassigned room',
                    'amount' => round((float) $charge->amount, 2),
                    'occurred_at' => optional($charge->created_at)?->toIso8601String(),
                ];
            });

        $recentPayments = $this->successfulPaymentsQuery()
            ->with(['booking:id,booking_code,guest_name,guest_email', 'room:id,name,room_number'])
            ->latest(DB::raw('COALESCE(paid_at, created_at)'))
            ->limit(12)
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'id' => 'payment-' . $payment->id,
                    'kind' => 'payment',
                    'label' => 'Payment received',
                    'booking_code' => $payment->booking?->booking_code,
                    'guest' => $payment->booking?->guest_name ?: $payment->booking?->guest_email ?: 'Guest',
                    'room' => $payment->room?->name ?: $payment->room?->room_number ?: 'Unassigned room',
                    'amount' => round((float) ($payment->amount_paid ?? $payment->amount ?? 0), 2),
                    'occurred_at' => optional($payment->paid_at ?? $payment->created_at)?->toIso8601String(),
                ];
            });

        $recentTransactions = $recentCharges
            ->concat($recentPayments)
            ->sortByDesc('occurred_at')
            ->take(12)
            ->values();

        return Inertia::render('Admin/Reports/Dashboard', [
            'mode' => 'finance',
            'title' => 'Finance Dashboard',
            'kpis' => [
                'charges' => [
                    'count' => (clone $chargeSummaryQuery)->count(),
                    'total' => round((float) (clone $chargeSummaryQuery)->sum('amount'), 2),
                ],
                'payments' => [
                    'count' => (clone $paymentSummaryQuery)->count(),
                    'total' => round((float) (clone $paymentSummaryQuery)->sum(DB::raw('COALESCE(amount_paid, amount)')), 2),
                    'today_total' => round((float) $this->successfulPaymentsQuery()
                        ->where(function ($query) use ($today) {
                            $query->whereDate('paid_at', $today->toDateString())
                                ->orWhere(function ($nested) use ($today) {
                                    $nested->whereNull('paid_at')
                                        ->whereDate('created_at', $today->toDateString());
                                });
                        })
                        ->sum(DB::raw('COALESCE(amount_paid, amount)')), 2),
                ],
                'outstanding' => [
                    'count' => $outstandingBookings->count(),
                    'total' => round((float) $outstandingBookings->sum('outstanding'), 2),
                ],
                'periods' => [
                    'open' => AccountingPeriod::query()->where('is_closed', false)->count(),
                ],
            ],
            'links' => [
                'primary' => route('finance.outstanding-balances.index', ['view' => 'booking']),
                'secondary' => route('finance.audit.index'),
                'tertiary' => route('finance.accounting-periods.index'),
                'quaternary' => route('finance.reports.daily-revenue'),
            ],
            'recentTransactions' => $recentTransactions,
            'charts' => [
                [
                    'title' => 'Revenue Trend',
                    'endpoint' => '/finance/reports/charts/revenue',
                ],
            ],
        ]);
    }

    protected function successfulPaymentsQuery()
    {
        return Payment::query()->whereIn('status', ['successful', 'completed']);
    }
}
