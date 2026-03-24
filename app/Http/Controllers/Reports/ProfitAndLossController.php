<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\Reports\ProfitAndLossService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitAndLossController extends Controller
{
    public function __construct(
        protected ProfitAndLossService $pnl
    ) {}

    public function index(Request $request)
    {
        $from = Carbon::parse(
            $request->input('from', now()->startOfMonth())
        );

        $to = Carbon::parse(
            $request->input('to', now()->endOfMonth())
        );

        return Inertia::render('Reports/ProfitAndLoss', [
            'report' => $this->pnl->generate($from, $to),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'routePrefix' => str_starts_with($request->path(), 'finance/') ? 'finance' : 'admin',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to = Carbon::parse($request->input('to', now()->endOfMonth()));
        $report = $this->pnl->generate($from, $to);

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Section', 'Account', 'Amount']);

            foreach ($report['revenue'] as $row) {
                fputcsv($handle, ['Revenue', $row['account'], $row['amount']]);
            }

            foreach ($report['expenses'] as $row) {
                fputcsv($handle, ['Expense', $row['account'], $row['amount']]);
            }

            fputcsv($handle, ['Totals', 'Net Profit', $report['totals']['net_profit']]);
            fclose($handle);
        }, 'profit-loss.csv', ['Content-Type' => 'text/csv']);
    }
}
