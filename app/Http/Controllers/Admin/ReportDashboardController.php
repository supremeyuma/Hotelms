<?php
// app/Http/Controllers/Admin/ReportDashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Booking;
use App\Models\Room;
use App\Services\RoomBillingService;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\OccupancyReportService;
use App\Services\Reports\StaffReportService;
use App\Services\Reports\InventoryReportService;
use Inertia\Inertia;
use Carbon\Carbon;

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
        RevenueReportService $revenue,
        RoomBillingService $billingService
    ) {
        $roomsWithBalances = Room::with(['booking', 'booking.rooms'])
            ->whereHas('booking', fn ($query) => $query->whereNotIn('status', ['cancelled', 'completed']))
            ->get()
            ->filter(fn ($room) => $billingService->outstanding($room) > 0);

        return Inertia::render('Admin/Reports/Dashboard', [
            'mode' => 'finance',
            'title' => 'Finance Dashboard',
            'kpis' => [
                'revenue' => $revenue->summary(),
                'daily_revenue' => [
                    'total' => round(
                        Booking::whereDate('created_at', Carbon::today())
                            ->whereIn('status', ['confirmed', 'active', 'checked_in', 'completed'])
                            ->sum('total_amount'),
                        2
                    ),
                ],
                'outstanding' => [
                    'count' => $roomsWithBalances->count(),
                    'total' => round($roomsWithBalances->sum(fn ($room) => $billingService->outstanding($room)), 2),
                ],
                'periods' => [
                    'open' => AccountingPeriod::query()->where('is_closed', false)->count(),
                ],
            ],
            'links' => [
                'primary' => route('finance.reports.revenue'),
                'secondary' => route('finance.outstanding-balances.index'),
                'tertiary' => route('finance.accounting-periods.index'),
                'quaternary' => route('finance.audit.index'),
            ],
            'charts' => [
                [
                    'title' => 'Revenue Trend',
                    'endpoint' => '/finance/reports/charts/revenue',
                ],
            ],
        ]);
    }
}
