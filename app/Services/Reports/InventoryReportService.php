<?php

namespace App\Services\Reports;

use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function query(array $filters)
    {
        return InventoryLog::with(['inventoryItem','staff'])
            ->when($filters['from'] ?? null, fn($q,$v)=>$q->whereDate('created_at','>=',$v))
            ->when($filters['to'] ?? null, fn($q,$v)=>$q->whereDate('created_at','<=',$v));
    }

    public function summary()
    {
        return [
            'usage' => InventoryLog::sum(DB::raw('ABS(`change`)'))
        ];
    }
}
