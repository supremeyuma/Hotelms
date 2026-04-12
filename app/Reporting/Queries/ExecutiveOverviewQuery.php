<?php

namespace App\Reporting\Queries;

use App\Models\ReportingDepartmentDailyFact;
use App\Models\ReportingException;
use App\Models\ReportingOrderFact;
use App\Models\ReportingRoomDailyFact;
use App\Reporting\ReportingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ExecutiveOverviewQuery extends ReportingService
{
    public function __construct()
    {
        // Initialize with empty query, will be built per metric
    }

    /**
     * Get hotel overview metrics for today
     */
    public function getTodayOverview()
    {
        $today = Carbon::today();

        return [
            'occupancy' => $this->getOccupancyMetrics($today),
            'arrivals_departures' => $this->getArrivalsAndDepartures($today),
            'department_status' => $this->getDepartmentStatus($today),
            'exceptions' => $this->getOpenExceptions(),
            'financial_pulse' => $this->getFinancialPulse($today),
            'backlog_alerts' => $this->getBacklogAlerts(),
        ];
    }

    /**
     * Get occupancy metrics
     */
    public function getOccupancyMetrics($date)
    {
        $occupiedRooms = ReportingRoomDailyFact::where('date', $date)
            ->where('occupied', true)
            ->count();

        $totalRooms = ReportingRoomDailyFact::where('date', $date)->count();
        $rate = $totalRooms > 0 ? ($occupiedRooms / $totalRooms) * 100 : 0;

        return [
            'occupied_rooms' => $occupiedRooms,
            'total_rooms' => $totalRooms,
            'occupancy_rate' => round($rate, 2),
            'guest_count' => ReportingRoomDailyFact::where('date', $date)->sum('guest_count'),
            'out_of_service' => ReportingRoomDailyFact::where('date', $date)
                ->where('out_of_service', true)
                ->count(),
        ];
    }

    /**
     * Get arrivals and departures
     */
    public function getArrivalsAndDepartures($date)
    {
        // This would need booking facts with actual check-in dates
        return [
            'expected_arrivals' => 0,
            'actual_arrivals' => 0,
            'expected_departures' => 0,
            'actual_departures' => 0,
        ];
    }

    /**
     * Get department status
     */
    public function getDepartmentStatus($date)
    {
        $departments = ['kitchen', 'bar', 'laundry', 'housekeeping', 'maintenance'];

        return ReportingDepartmentDailyFact::where('date', $date)
            ->whereIn('department', $departments)
            ->get()
            ->map(function ($dept) {
                return [
                    'department' => $dept->department,
                    'requests_received' => $dept->requests_received,
                    'backlog_open' => $dept->backlog_open,
                    'avg_response_minutes' => $dept->avg_response_minutes,
                    'sla_breaches' => $dept->sla_breaches,
                ];
            });
    }

    /**
     * Get open exceptions
     */
    public function getOpenExceptions($limit = 10)
    {
        return ReportingException::where('status', '!=', 'resolved')
            ->orderBy('severity', 'desc')
            ->orderBy('detected_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get financial pulse
     */
    public function getFinancialPulse($date)
    {
        return [
            'charges_posted' => 0,
            'payments_collected' => 0,
            'refunds_issued' => 0,
            'cash_on_hand' => 0,
        ];
    }

    /**
     * Get backlog alerts
     */
    public function getBacklogAlerts()
    {
        return ReportingDepartmentDailyFact::where('backlog_open', '>', 0)
            ->orderByDesc('backlog_open')
            ->get()
            ->map(function ($item) {
                return [
                    'department' => $item->department,
                    'backlog' => $item->backlog_open,
                ];
            });
    }
}
