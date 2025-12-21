<?php

// app/Http/Controllers/Admin/Reports/CleanerPerformanceController.php
namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CleanerPerformanceService;
use Inertia\Inertia;

class CleanerPerformanceController extends Controller
{
    public function index(CleanerPerformanceService $service)
    {
        return Inertia::render('Admin/Reports/CleanerPerformance', [
            'rows' => $service->summary()
        ]);
    }
}
