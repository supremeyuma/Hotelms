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
        $filters = $request->only(['search', 'role', 'department', 'status']);
        $query = $service->query($filters);
        $rows = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/Reports/Staff', [
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $service->summary(),
            'roles' => $service->roles(),
            'departments' => $service->departments(),
            'routePrefix' => 'admin.staff',
        ]);
    }

    public function export(string $format, Request $request, StaffReportService $service)
    {
        $data = $service->query($request->all())->get();
        return Excel::download(new GenericExport($data), "staff.$format");
    }
}
