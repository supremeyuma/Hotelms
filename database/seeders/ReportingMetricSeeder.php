<?php

namespace Database\Seeders;

use App\Models\ReportingMetricDefinition;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReportingMetricSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $metrics = [
            [
                'metric_key' => 'occupancy_rate',
                'metric_name' => 'Occupancy Rate',
                'business_meaning' => 'Percentage of occupied rooms relative to total available rooms',
                'calculation_type' => 'aggregation',
                'source_tables' => ['reporting_room_daily_facts'],
                'transformation_rules' => 'COUNT(occupied=true) / COUNT(*) * 100',
                'time_basis' => 'occurrence',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'percentage',
                'owner' => 'operations',
            ],
            [
                'metric_key' => 'department_backlog',
                'metric_name' => 'Department Backlog',
                'business_meaning' => 'Number of open work items not completed by end of day',
                'calculation_type' => 'aggregation',
                'source_tables' => ['reporting_department_daily_facts'],
                'transformation_rules' => 'SUM(backlog_open)',
                'time_basis' => 'completion',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'count',
                'owner' => 'department',
            ],
            [
                'metric_key' => 'average_response_time',
                'metric_name' => 'Average Response Time',
                'business_meaning' => 'Average time from request received to work started',
                'calculation_type' => 'aggregation',
                'source_tables' => ['reporting_department_daily_facts'],
                'transformation_rules' => 'AVG(avg_response_minutes)',
                'time_basis' => 'completion',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'minutes',
                'owner' => 'department',
            ],
            [
                'metric_key' => 'sla_compliance_rate',
                'metric_name' => 'SLA Compliance Rate',
                'business_meaning' => 'Percentage of requests completed within SLA timeframe',
                'calculation_type' => 'derived',
                'source_tables' => ['reporting_department_daily_facts'],
                'transformation_rules' => '(requests_completed - sla_breaches) / requests_completed * 100',
                'time_basis' => 'completion',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'percentage',
                'owner' => 'department',
            ],
            [
                'metric_key' => 'outstanding_balance',
                'metric_name' => 'Outstanding Balance',
                'business_meaning' => 'Total amount owed by guests across active bookings',
                'calculation_type' => 'aggregation',
                'source_tables' => ['reporting_booking_facts'],
                'transformation_rules' => 'SUM(outstanding_balance WHERE booking is active)',
                'time_basis' => 'posting',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'currency',
                'owner' => 'finance',
            ],
            [
                'metric_key' => 'daily_revenue',
                'metric_name' => 'Daily Revenue',
                'business_meaning' => 'Total payments collected in a single day',
                'calculation_type' => 'aggregation',
                'source_tables' => ['reporting_payment_facts'],
                'transformation_rules' => 'SUM(amount WHERE status=completed)',
                'exclusions' => ['reversed payments'],
                'time_basis' => 'posting',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'currency',
                'owner' => 'finance',
            ],
            [
                'metric_key' => 'maintenance_sla_breach',
                'metric_name' => 'Maintenance SLA Breach Rate',
                'business_meaning' => 'Percentage of maintenance tickets not resolved within SLA',
                'calculation_type' => 'derived',
                'source_tables' => ['reporting_maintenance_facts'],
                'transformation_rules' => 'sla_breach_count / total_resolved * 100',
                'time_basis' => 'completion',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'percentage',
                'owner' => 'maintenance',
            ],
            [
                'metric_key' => 'laundry_completion_rate',
                'metric_name' => 'Laundry Completion Rate',
                'business_meaning' => 'Percentage of laundry orders delivered on time',
                'calculation_type' => 'derived',
                'source_tables' => ['reporting_laundry_facts'],
                'transformation_rules' => '(delivered_count - sla_breach_count) / delivered_count * 100',
                'time_basis' => 'completion',
                'default_granularity' => 'daily',
                'unit_of_measure' => 'percentage',
                'owner' => 'laundry',
            ],
        ];

        foreach ($metrics as $metric) {
            ReportingMetricDefinition::updateOrCreate(
                ['metric_key' => $metric['metric_key']],
                [
                    ...$metric,
                    'effective_from' => Carbon::now(),
                    'is_active' => true,
                    'is_tested' => false,
                ]
            );
        }

        $this->command->info('Reporting metrics seeded successfully');
    }
}
