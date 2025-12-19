<?php

namespace App\Services\Reports;

use App\Models\Booking;
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

    public function summary()
    {
        $totalRooms = DB::table('rooms')->count();
        $occupied = Booking::count();
        return round(($occupied / max($totalRooms,1)) * 100,2);
    }
}
