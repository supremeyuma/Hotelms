<?php

return [
    /*
     * SLA (Service Level Agreement) thresholds
     */
    'maintenance_sla_hours' => env('MAINTENANCE_SLA_HOURS', 24),
    'laundry_sla_hours' => env('LAUNDRY_SLA_HOURS', 4),
    'kitchen_sla_minutes' => env('KITCHEN_SLA_MINUTES', 45),
    'bar_sla_minutes' => env('BAR_SLA_MINUTES', 30),

    /*
     * Exception detection thresholds
     */
    'repeat_issue_threshold' => env('REPEAT_ISSUE_THRESHOLD', 3),
    'repeat_issue_days' => env('REPEAT_ISSUE_DAYS', 7),

    /*
     * Report export settings
     */
    'export_max_rows' => env('EXPORT_MAX_ROWS', 10000),
    'export_formats' => ['csv', 'pdf', 'xlsx'],

    /*
     * Dashboard refresh intervals (seconds)
     */
    'dashboard_refresh_interval' => env('DASHBOARD_REFRESH_INTERVAL', 300),

    /*
     * Archive old reporting data after (days)
     */
    'archive_data_after_days' => env('ARCHIVE_DATA_AFTER_DAYS', 90),

    /*
     * Metric calculation settings
     */
    'metrics' => [
        'occupancy_rate' => [
            'unit' => 'percentage',
            'frequency' => 'daily',
            'owner' => 'operations',
        ],
        'backlog' => [
            'unit' => 'count',
            'frequency' => 'hourly',
            'owner' => 'department',
        ],
        'average_response_time' => [
            'unit' => 'minutes',
            'frequency' => 'daily',
            'owner' => 'department',
        ],
        'sla_compliance_rate' => [
            'unit' => 'percentage',
            'frequency' => 'daily',
            'owner' => 'department',
        ],
    ],
];
