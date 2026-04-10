<?php
// app/Http/Controllers/Admin/Reports/InventoryReportController.php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\Reports\InventoryReportService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GenericExport;

class InventoryReportController extends Controller
{
    public function index(Request $request, InventoryReportService $service)
    {
        // Ensure chart() is called with integer days, not full request array
        $chartData = $service->chart((int) $request->input('days', 30));

        $rows = $service->query($request->all())
            ->with(['inventoryItem', 'staff'])
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Reports/Inventory', [
            'rows' => $rows,
            'chart' => $chartData,
            'filters' => $request->all(),
            'items' => InventoryItem::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name']),
            'types' => ['in', 'out', 'transfer_in', 'transfer_out', 'adjustment'],
        ]);
    }

    public function export(string $format, Request $request, InventoryReportService $service)
    {
        $data = $service->query($request->all())->with(['inventoryItem', 'staff'])->get();

        return Excel::download(new GenericExport($data), "inventory.$format");
    }
}
