<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\OccupancyReportService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;

class OccupancyReportController extends Controller
{
    public function index(Request $request, OccupancyReportService $service)
    {
        return Inertia::render('Admin/Reports/Occupancy', [
            'rows' => $service->query($request->all()),
            'summary' => $service->summary()
        ]);
    }

    public function export(string $format, Request $request, OccupancyReportService $service)
    {
        return Excel::download(
            new GenericExport($service->query($request->all())),
            "occupancy.$format"
        );
    }
}
