<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingMetricDefinition extends Model
{
    protected $table = 'reporting_metric_definitions';
    protected $fillable = [
        'metric_key',
        'metric_name',
        'business_meaning',
        'calculation_type',
        'source_tables',
        'transformation_rules',
        'exclusions',
        'time_basis',
        'default_granularity',
        'unit_of_measure',
        'owner',
        'version',
        'effective_from',
        'effective_to',
        'test_cases',
        'is_tested',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'is_tested' => 'boolean',
            'is_active' => 'boolean',
            'source_tables' => 'json',
            'exclusions' => 'json',
            'test_cases' => 'json',
        ];
    }

    public static function getActiveByKey($metricKey)
    {
        return self::where('metric_key', $metricKey)
            ->where('is_active', true)
            ->where('effective_from', '<=', now())
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            })
            ->latest('version')
            ->first();
    }

    public function markTested()
    {
        $this->update(['is_tested' => true]);
    }

    public function deprecate()
    {
        $this->update([
            'is_active' => false,
            'effective_to' => now(),
        ]);
    }
}
