<?php

// app/Services/Reports/CleanerPerformanceService.php
namespace App\Services\Reports;

use App\Models\CleaningLog;
use Illuminate\Support\Facades\DB;

class CleanerPerformanceService
{
    public function summary()
    {
        return CleaningLog::select(
            'user_id',
            DB::raw('COUNT(*) as total_actions'),
            DB::raw("SUM(action = 'clean') as rooms_cleaned")
        )
        ->groupBy('user_id')
        ->with('user')
        ->get();
    }
}
