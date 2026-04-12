<?php

namespace App\Reporting\Queries;

use App\Models\ReportingEvent;
use App\Reporting\ReportingService;

class RoomTimelineQuery extends ReportingService
{
    private $roomId;

    public function __construct($roomId)
    {
        $this->roomId = $roomId;
        $this->baseQuery = ReportingEvent::where('room_id', $roomId)->orderBy('occurred_at', 'desc');
    }

    /**
     * Get room timeline for a specific date range
     */
    public function forDateRange($startDate, $endDate)
    {
        $this->baseQuery->whereBetween('occurred_at', [$startDate, $endDate]);

        return $this;
    }

    /**
     * Get only specific event types
     */
    public function filterByEventType($eventType)
    {
        $this->baseQuery->where('event_type', $eventType);

        return $this;
    }

    /**
     * Get timeline grouped by department
     */
    public function groupedByDepartment()
    {
        return $this->baseQuery->get()->groupBy('department');
    }

    /**
     * Get timeline with full context
     */
    public function withContext()
    {
        return $this->baseQuery->with(['room', 'booking', 'user'])->get();
    }

    /**
     * Get summary statistics for room
     */
    public function getSummaryStats()
    {
        $events = $this->baseQuery->get();

        return [
            'total_events' => $events->count(),
            'departments_involved' => $events->pluck('department')->unique()->values(),
            'event_types' => $events->pluck('event_type')->unique()->values(),
            'total_amount' => $events->sum('amount'),
            'last_event' => $events->first()?->occurred_at,
        ];
    }
}
