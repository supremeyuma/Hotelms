<?php

namespace App\Services\Reports;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    public function query(array $filters)
    {
        return Booking::query()
            ->when($filters['from'] ?? null, fn($q,$v)=>$q->whereDate('created_at','>=',$v))
            ->when($filters['to'] ?? null, fn($q,$v)=>$q->whereDate('created_at','<=',$v))
            ->where('status','confirmed');
    }

    public function summary()
    {
        $total = Booking::sum('total_amount');
        $adr = Booking::avg('total_amount');
        return compact('total','adr');
    }
}
