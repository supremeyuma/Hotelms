<?php
// app/Services/Reports/RevenueReportService.php

namespace App\Services\Reports;

use App\Models\Booking;
use Carbon\Carbon;

class RevenueReportService
{

    public function query(array $filters)
    {
        return Booking::query()
            ->when($filters['from'] ?? null, fn($q,$v)=>$q->whereDate('created_at','>=',$v))
            ->when($filters['to'] ?? null, fn($q,$v)=>$q->whereDate('created_at','<=',$v))
            ->where('status','confirmed');
    }
    
    public function chart(int $days = 30): array
    {
        $from = Carbon::now()->subDays($days);

        $data = Booking::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('status', 'confirmed')
            ->whereDate('created_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date'),
            'values' => $data->pluck('total'),
        ];
    }

    public function summary(): array
    {
        $total = Booking::where('status', 'confirmed')->sum('total_amount');
        $adr = Booking::where('status', 'confirmed')->avg('total_amount');

        return [
            'revenue' => round($total, 2),
            'adr' => round($adr, 2),
        ];
    }
}
