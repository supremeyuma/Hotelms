<?php

namespace App\Reporting\Queries;

use App\Models\ReportingException;
use App\Reporting\ReportingService;

class ExceptionsQuery extends ReportingService
{
    public function __construct()
    {
        $this->baseQuery = ReportingException::query();
    }

    /**
     * Get open exceptions
     */
    public function getOpenOnly()
    {
        $this->baseQuery->where('status', '!=', 'resolved');

        return $this;
    }

    /**
     * Get critical exceptions
     */
    public function getCriticalOnly()
    {
        $this->baseQuery->where('severity', 'critical');

        return $this;
    }

    /**
     * Get escalated exceptions
     */
    public function getEscalatedOnly()
    {
        $this->baseQuery->where('escalated', true);

        return $this;
    }

    /**
     * Get exceptions by type
     */
    public function getByType($exceptionType)
    {
        $this->baseQuery->where('exception_type', $exceptionType);

        return $this;
    }

    /**
     * Get aging exceptions (open and not recently updated)
     */
    public function getAgingExceptions($daysThreshold = 7)
    {
        $this->baseQuery->where('status', 'open')
            ->where('detected_at', '<', now()->subDays($daysThreshold));

        return $this;
    }

    /**
     * Get exception summary by severity
     */
    public function getSummaryBySeverity()
    {
        return ReportingException::where('status', '!=', 'resolved')
            ->groupBy('severity')
            ->selectRaw('severity, COUNT(*) as count')
            ->get();
    }

    /**
     * Get exceptions by department
     */
    public function getByDepartment($department)
    {
        $this->baseQuery->where('department', $department);

        return $this;
    }
}
