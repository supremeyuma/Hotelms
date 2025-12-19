<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\{
    RevenueReportService,
    OccupancyReportService,
    StaffReportService,
    InventoryReportService
};
use Inertia\Inertia;
use Illuminate\Http\Request;

class ReportDashboardController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Admin/Reports/Dashboard', [
            'kpis' => [
                'revenue' => app(RevenueReportService::class)->summary(),
                'occupancy' => app(OccupancyReportService::class)->summary(),
                'staff' => app(StaffReportService::class)->summary(),
                'inventory' => app(InventoryReportService::class)->summary(),
            ]
        ]);
    }
}
