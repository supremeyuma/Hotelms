<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\Reports\DailyRevenueService;

class DailyRevenueController extends Controller
{
    public function __construct(
        protected DailyRevenueService $service
    ) {}

    public function index(Request $request)
    {
        $date = Carbon::parse(
            $request->input('date', now())
        );

        return Inertia::render('Reports/DailyRevenue', [
            'date' => $date->toDateString(),
            'revenue' => $this->service->generate($date),
        ]);
    }
}
