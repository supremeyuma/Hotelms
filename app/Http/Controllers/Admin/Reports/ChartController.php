<?php
// app/Http/Controllers/Admin/Reports/ChartController.php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\OccupancyReportService;
use App\Services\Reports\RevenueReportService;
use App\Services\Reports\InventoryReportService;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function occupancy(Request $request, OccupancyReportService $service)
    {
        return $service->chart($request->integer('days', 30));
    }

    public function revenue(Request $request, RevenueReportService $service)
    {
        return $service->chart($request->integer('days', 30));
    }

    public function inventory(Request $request, InventoryReportService $service)
    {
        return $service->chart($request->integer('days', 30));
    }
}
