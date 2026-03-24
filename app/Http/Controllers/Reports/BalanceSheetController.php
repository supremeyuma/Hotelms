<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\Reports\BalanceSheetService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheetController extends Controller
{
    public function __construct(
        protected BalanceSheetService $service
    ) {}

    public function index(Request $request)
    {
        $asOf = Carbon::parse(
            $request->input('as_of', now())
        );

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $this->service->generate($asOf),
            'filters' => [
                'as_of' => $asOf->toDateString(),
            ],
            'routePrefix' => str_starts_with($request->path(), 'finance/') ? 'finance' : 'admin',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $asOf = Carbon::parse($request->input('as_of', now()));
        $report = $this->service->generate($asOf);

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Section', 'Account', 'Balance']);

            foreach (['assets' => 'Asset', 'liabilities' => 'Liability', 'equity' => 'Equity'] as $key => $label) {
                foreach ($report[$key] as $row) {
                    fputcsv($handle, [$label, $row['account'], $row['balance']]);
                }
            }

            fputcsv($handle, ['Totals', 'Assets', $report['totals']['assets']]);
            fputcsv($handle, ['Totals', 'Liabilities & Equity', $report['totals']['liabilities_equity']]);
            fclose($handle);
        }, 'balance-sheet.csv', ['Content-Type' => 'text/csv']);
    }
}
