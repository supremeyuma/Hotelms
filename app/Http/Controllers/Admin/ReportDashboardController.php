<?php
// app/Http/Controllers/Admin/ReportDashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\OccupancyReportService;
use App\Services\Reports\StaffReportService;
use App\Services\Reports\InventoryReportService;
use Inertia\Inertia;

class ReportDashboardController extends Controller
{
    public function index(
        RevenueReportService $revenue,
        OccupancyReportService $occupancy,
        StaffReportService $staff,
        InventoryReportService $inventory
    ) {
        return Inertia::render('Admin/Reports/Dashboard', [
            'kpis' => [
                'revenue'   => $revenue->summary(),
                'occupancy' => $occupancy->summary(),
                'staff'     => $staff->summary(),
                'inventory' => $inventory->summary(),
            ],
        ]);
    }
}
