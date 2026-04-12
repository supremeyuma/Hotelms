<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportingRoomDailyFact;
use App\Reporting\Queries\ExecutiveOverviewQuery;
use App\Reporting\Queries\ExceptionsQuery;
use Inertia\Inertia;

class ReportingDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:manager|md']);
    }

    /**
     * Show executive dashboard
     */
    public function executiveOverview()
    {
        $query = new ExecutiveOverviewQuery();
        $overview = $query->getTodayOverview();

        $exceptionsQuery = new ExceptionsQuery();
        $openExceptions = $exceptionsQuery->getOpenOnly()->orderBy('severity', 'desc')->limit(5)->get();

        return Inertia::render('Admin/Reports/ExecutiveOverview', [
            'overview' => $overview,
            'recentExceptions' => $openExceptions,
        ]);
    }

    /**
     * Show room intelligence dashboard
     */
    public function roomIntelligence($roomId)
    {
        $room = \App\Models\Room::findOrFail($roomId);
        
        // Verify access
        $this->authorize('view', $room);

        $roomFacts = ReportingRoomDailyFact::where('room_id', $roomId)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return Inertia::render('Admin/Reports/RoomIntelligence', [
            'room' => $room,
            'facts' => $roomFacts,
        ]);
    }

    /**
     * Show exceptions dashboard
     */
    public function exceptions()
    {
        $exceptionsQuery = new ExceptionsQuery();
        $exceptions = $exceptionsQuery->getOpenOnly()
            ->orderBy('severity', 'desc')
            ->orderBy('detected_at', 'desc')
            ->paginate(20);

        $summary = $exceptionsQuery->getSummaryBySeverity();

        return Inertia::render('Admin/Reports/ExceptionsDashboard', [
            'exceptions' => $exceptions,
            'summary' => $summary,
        ]);
    }

    /**
     * Show department command center
     */
    public function departmentCommand($department)
    {
        $allowed = ['kitchen', 'bar', 'laundry', 'housekeeping', 'maintenance'];
        if (! in_array($department, $allowed)) {
            abort(404);
        }

        $query = new \App\Reporting\Queries\DepartmentPerformanceQuery($department);
        $performance = $query->forDateRange(now()->subDays(30), now());

        return Inertia::render('Admin/Reports/DepartmentCommand', [
            'department' => $department,
            'data' => [
                'backlog' => $query->getBacklogTrend(),
                'sla' => $query->getSLAPerformance(),
                'revenue' => $query->getRevenueImpact(),
                'staffing' => $query->getStaffingMetrics(),
            ],
        ]);
    }
}
