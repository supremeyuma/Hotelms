<?php
// app/Services/Reports/InventoryReportService.php

namespace App\Services\Reports;

use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class InventoryReportService
{
    public function chart(int $days = 30): array
    {
        $from = Carbon::now()->subDays($days);

        $data = InventoryLog::selectRaw('DATE(created_at) as date, SUM(ABS(`change`)) as total')
            ->whereDate('created_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date'),
            'values' => $data->pluck('total'),
        ];
    }

     public function query(array $filters)
    {
        return InventoryLog::with(['inventoryItem','staff'])
            ->when($filters['from'] ?? null, fn($q,$v)=>$q->whereDate('created_at','>=',$v))
            ->when($filters['to'] ?? null, fn($q,$v)=>$q->whereDate('created_at','<=',$v));
    }

    public function summary(): array
    {
        return [
            'usage' => InventoryLog::sum(DB::raw('ABS(`change`)')),
        ];
    }
}
