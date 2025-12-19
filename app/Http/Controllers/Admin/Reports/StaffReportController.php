<?php
// app/Http/Controllers/Admin/Reports/StaffReportController.php

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
        $query = $service->query($request->all());
        $rows = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/Reports/Staff', [
            'rows' => $rows,
            'filters' => $request->all(),
        ]);
    }

    public function export(string $format, Request $request, StaffReportService $service)
    {
        $data = $service->query($request->all())->get();
        return Excel::download(new GenericExport($data), "staff.$format");
    }
}
