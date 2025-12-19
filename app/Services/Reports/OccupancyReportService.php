<?php
// app/Services/Reports/OccupancyReportService.php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OccupancyReportService
{
    public function query(array $filters)
    {
        return Booking::selectRaw('DATE(check_in) date, COUNT(*) bookings')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
    public function chart(int $days = 30): array
    {
        $from = Carbon::now()->subDays($days);

        $data = Booking::selectRaw('DATE(check_in) as date, COUNT(*) as total')
            ->whereDate('check_in', '>=', $from)
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
        $rooms = Room::count();
        $occupied = Booking::whereDate('check_in', today())->count();

        $rate = $rooms > 0
            ? round(($occupied / $rooms) * 100, 2)
            : 0;

        return [
            'occupancy' => $rate,
        ];
    }
}
