<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\Reports\DailyRevenueService;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'filters' => [
                'date' => $date->toDateString(),
            ],
            'routePrefix' => str_starts_with($request->path(), 'finance/') ? 'finance' : 'admin',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $date = Carbon::parse($request->input('date', now()));
        $revenue = $this->service->generate($date);

        return response()->streamDownload(function () use ($revenue) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Department', 'Amount']);

            foreach ($revenue as $row) {
                fputcsv($handle, [$row['department'], $row['amount']]);
            }

            fclose($handle);
        }, 'daily-revenue.csv', ['Content-Type' => 'text/csv']);
    }
}
