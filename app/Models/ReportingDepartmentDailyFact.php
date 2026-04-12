<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingDepartmentDailyFact extends Model
{
    protected $table = 'reporting_department_daily_facts';
    protected $fillable = [
        'department',
        'date',
        'requests_received',
        'requests_completed',
        'requests_cancelled',
        'requests_escalated',
        'backlog_open',
        'avg_response_minutes',
        'avg_completion_minutes',
        'sla_breaches',
        'revenue',
        'refunds',
        'cost_of_consumption',
        'staff_on_duty',
        'assignments_per_staff',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
