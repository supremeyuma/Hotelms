<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingStaffDailyFact extends Model
{
    protected $table = 'reporting_staff_daily_facts';
    protected $fillable = [
        'staff_id',
        'department',
        'date',
        'assignments_received',
        'assignments_completed',
        'assignments_reassigned',
        'open_work_end_of_day',
        'avg_completion_minutes',
        'escalations',
        'refunds_or_reversals',
        'charges_posted',
        'payments_collected',
        'error_count',
        'repeat_requests',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
