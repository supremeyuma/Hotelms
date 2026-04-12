<?php

namespace App\Reporting;

use Illuminate\Database\Eloquent\Builder;

class ReportingService
{
    protected $baseQuery;

    /**
     * Add date range filter
     */
    public function filterByDateRange($startDate = null, $endDate = null)
    {
        if ($startDate) {
            $this->baseQuery->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $this->baseQuery->where('date', '<=', $endDate);
        }

        return $this;
    }

    /**
     * Add department filter
     */
    public function filterByDepartment($department)
    {
        $this->baseQuery->where('department', $department);

        return $this;
    }

    /**
     * Add room filter
     */
    public function filterByRoom($roomId)
    {
        $this->baseQuery->where('room_id', $roomId);

        return $this;
    }

    /**
     * Add booking filter
     */
    public function filterByBooking($bookingId)
    {
        $this->baseQuery->where('booking_id', $bookingId);

        return $this;
    }

    /**
     * Add status filter
     */
    public function filterByStatus($status)
    {
        $this->baseQuery->where('status', $status);

        return $this;
    }

    /**
     * Add severity filter for exceptions
     */
    public function filterBySeverity($severity)
    {
        $this->baseQuery->where('severity', $severity);

        return $this;
    }

    /**
     * Get results as collection
     */
    public function get()
    {
        return $this->baseQuery->get();
    }

    /**
     * Get single result
     */
    public function first()
    {
        return $this->baseQuery->first();
    }

    /**
     * Get paginated results
     */
    public function paginate($perPage = 15)
    {
        return $this->baseQuery->paginate($perPage);
    }

    /**
     * Get count
     */
    public function count()
    {
        return $this->baseQuery->count();
    }

    /**
     * Add order by
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->baseQuery->orderBy($column, $direction);

        return $this;
    }

    /**
     * Get base query for extension
     */
    public function getQuery(): Builder
    {
        return $this->baseQuery;
    }
}
