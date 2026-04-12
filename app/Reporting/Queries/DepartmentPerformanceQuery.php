<?php

namespace App\Reporting\Queries;

use App\Models\ReportingDepartmentDailyFact;
use App\Reporting\ReportingService;

class DepartmentPerformanceQuery extends ReportingService
{
    private $department;

    public function __construct($department)
    {
        $this->department = $department;
        $this->baseQuery = ReportingDepartmentDailyFact::where('department', $department)
            ->orderBy('date', 'desc');
    }

    /**
     * Get performance metrics for date range
     */
    public function forDateRange($startDate, $endDate)
    {
        $this->baseQuery->whereBetween('date', [$startDate, $endDate]);

        return $this;
    }

    /**
     * Get backlog items
     */
    public function getBacklogTrend()
    {
        return $this->baseQuery->get()->map(function ($fact) {
            return [
                'date' => $fact->date,
                'backlog' => $fact->backlog_open,
                'requests_received' => $fact->requests_received,
                'requests_completed' => $fact->requests_completed,
            ];
        });
    }

    /**
     * Get SLA performance
     */
    public function getSLAPerformance()
    {
        $facts = $this->baseQuery->get();
        $totalDays = $facts->count();
        $slaBreachDays = $facts->where('sla_breaches', '>', 0)->count();

        return [
            'total_days_tracked' => $totalDays,
            'sla_breach_days' => $slaBreachDays,
            'sla_compliance_rate' => $totalDays > 0 ? (($totalDays - $slaBreachDays) / $totalDays) * 100 : 100,
            'average_response_minutes' => $facts->avg('avg_response_minutes'),
            'average_completion_minutes' => $facts->avg('avg_completion_minutes'),
        ];
    }

    /**
     * Get revenue impact
     */
    public function getRevenueImpact()
    {
        $facts = $this->baseQuery->get();

        return [
            'total_revenue' => $facts->sum('revenue'),
            'total_refunds' => $facts->sum('refunds'),
            'net_revenue' => $facts->sum('revenue') - $facts->sum('refunds'),
            'average_daily_revenue' => $facts->avg('revenue'),
        ];
    }

    /**
     * Get staffing metrics
     */
    public function getStaffingMetrics()
    {
        $facts = $this->baseQuery->get();

        return [
            'average_staff_on_duty' => $facts->avg('staff_on_duty'),
            'average_assignments_per_staff' => $facts->avg('assignments_per_staff'),
            'total_assignments' => $facts->sum('requests_received'),
        ];
    }
}
