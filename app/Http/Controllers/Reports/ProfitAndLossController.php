<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\Reports\ProfitAndLossService;

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
        ]);
    }
}
