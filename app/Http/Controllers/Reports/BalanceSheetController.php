<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\Reports\BalanceSheetService;

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
        ]);
    }
}
