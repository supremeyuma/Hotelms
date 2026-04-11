<?php
// app/Http/Controllers/Admin/Reports/RevenueReportController.php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\RevenueReportService;
use App\Services\AuditLoggerService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;

class RevenueReportController extends Controller
{
    public function index(Request $request, RevenueReportService $service, AuditLoggerService $auditLogger)
    {
        $filters = $service->filters($request->all());
        $query = $service->query($filters);
        $rows = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        $auditLogger->log('report_viewed', 'Revenue', 0, $request->all());

        return Inertia::render('Admin/Reports/Revenue', [
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $service->summaryFor($filters),
            'routePrefix' => str_starts_with($request->path(), 'finance/') ? 'finance' : 'admin',
        ]);
    }

    public function export(string $format, Request $request, RevenueReportService $service)
    {
        $rows = $service->query($service->filters($request->all()))->get();
        return Excel::download(new GenericExport($rows), "revenue.$format");
    }
}
