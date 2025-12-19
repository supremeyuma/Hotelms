<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardReportService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(DashboardReportService $service)
    {
        return Inertia::render('Admin/Reports/Dashboard', $service->summary());
    }
}
