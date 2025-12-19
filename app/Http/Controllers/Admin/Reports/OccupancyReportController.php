<?php
// app/Http/Controllers/Admin/Reports/OccupancyReportController.php
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
        $rows = $service->query($request->all());
        $summary = $service->summary();

        return Inertia::render('Admin/Reports/Occupancy', [
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function export(string $format, Request $request, OccupancyReportService $service)
    {
        $data = $service->query($request->all());
        return Excel::download(new GenericExport($data), "occupancy.$format");
    }
}
