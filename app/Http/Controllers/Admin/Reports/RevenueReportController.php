<?php

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
        $data = $service->query($request->all())->paginate(25)->withQueryString();

        $auditLogger->log('report_viewed', 'Revenue', 0, $request->all());

        return Inertia::render('Admin/Reports/Revenue', [
            'rows' => $data,
            'filters' => $request->all()
        ]);
    }

    public function export(string $format, Request $request, RevenueReportService $service)
    {
        $rows = $service->query($request->all())->get();
        return Excel::download(new GenericExport($rows), "revenue.$format");
    }
}
