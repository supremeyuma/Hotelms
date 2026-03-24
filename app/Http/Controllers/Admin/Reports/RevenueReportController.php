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
        $query = $service->query($request->all());
        $rows = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        $auditLogger->log('report_viewed', 'Revenue', 0, $request->all());

        return Inertia::render('Admin/Reports/Revenue', [
            'rows' => $rows,
            'filters' => $request->all(),
            'routePrefix' => str_starts_with($request->path(), 'finance/') ? 'finance' : 'admin',
        ]);
    }

    public function export(string $format, Request $request, RevenueReportService $service)
    {
        $rows = $service->query($request->all())->get();
        return Excel::download(new GenericExport($rows), "revenue.$format");
    }
}
