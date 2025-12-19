<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\StaffReportService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;

class StaffReportController extends Controller
{
    public function index(Request $request, StaffReportService $service)
    {
        return Inertia::render('Admin/Reports/Staff', [
            'rows' => $service->query($request->all())->paginate(25)->withQueryString(),
            'filters' => $request->all()
        ]);
    }

    public function export(string $format, Request $request, StaffReportService $service)
    {
        return Excel::download(
            new GenericExport($service->query($request->all())->get()),
            "staff.$format"
        );
    }
}
