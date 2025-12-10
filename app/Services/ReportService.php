<?php
// app/Services/ReportService.php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\InventoryLog;
use App\Models\MaintenanceTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * ReportService
 *
 * Generates arrays suitable for charts and Inertia.
 */
class ReportService
{
    protected AuditLoggerService $audit;

    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Occupancy % and daily counts between dates
     */
    public function occupancyReport(string $from, string $to): array
    {
        $totalRooms = DB::table('rooms')->count();

        $bookingsPerDay = Booking::selectRaw('DATE(check_in) as date, COUNT(*) as count')
            ->whereBetween('check_in', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($r) => ['date' => $r->date, 'count' => (int)$r->count]);

        $occupiedDays = $bookingsPerDay->sum('count');
        $occupancy = $totalRooms ? round(($occupiedDays / ($totalRooms * max(1, $bookingsPerDay->count()))) * 100, 2) : 0;

        return [
            'total_rooms' => $totalRooms,
            'occupancy_percent' => $occupancy,
            'daily' => $bookingsPerDay,
        ];
    }

    /**
     * Revenue report
     */
    public function revenueReport(string $from, string $to): array
    {
        $revenue = Booking::whereBetween('created_at', [$from, $to])->sum('total_amount');

        $byDay = Booking::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_revenue' => $revenue,
            'by_day' => $byDay,
        ];
    }

    /**
     * Staff productivity: completed orders, avg resolution times
     */
    public function staffPerformance(string $from, string $to): array
    {
        $staff = User::role('staff')->get()->map(function ($u) use ($from, $to) {
            $completedOrders = $u->orders()->whereBetween('created_at', [$from, $to])->where('status', 'completed')->count();
            $maintenanceResolved = $u->maintenanceTickets()->whereBetween('updated_at', [$from, $to])->where('status', 'resolved')->count();
            return [
                'id' => $u->id,
                'name' => $u->name,
                'completed_orders' => $completedOrders,
                'maintenance_resolved' => $maintenanceResolved,
            ];
        });

        return ['staff' => $staff];
    }

    /**
     * Inventory usage report within date range
     */
    public function inventoryUsage(string $from, string $to): array
    {
        $usage = InventoryLog::selectRaw('inventory_item_id, SUM(ABS(change)) as used')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('inventory_item_id')
            ->get();

        return ['usage' => $usage];
    }

    /**
     * Maintenance turnaround: average resolve time
     */
    public function maintenanceTurnaround(string $from, string $to): array
    {
        $tickets = MaintenanceTicket::whereBetween('created_at', [$from, $to])->whereNotNull('closed_at')->get();

        $avg = $tickets->count() ? $tickets->avg(function ($t) { return $t->closed_at->diffInHours($t->created_at); }) : 0;

        return ['average_hours_to_close' => $avg, 'count' => $tickets->count()];
    }

    /**
     * Room service volume report
     */
    public function roomServiceVolume(string $from, string $to): array
    {
        $counts = Order::selectRaw('department, COUNT(*) as total')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('department')
            ->get();

        return ['by_department' => $counts];
    }
}
