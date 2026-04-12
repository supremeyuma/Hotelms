<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingMaintenanceFact extends Model
{
    protected $table = 'reporting_maintenance_facts';
    protected $fillable = [
        'maintenance_ticket_id',
        'room_id',
        'category',
        'severity',
        'status',
        'reported_at',
        'assigned_at',
        'in_progress_at',
        'resolved_at',
        'reopened_at',
        'assigned_staff_id',
        'reported_by_id',
        'response_minutes',
        'resolution_minutes',
        'reopen_count',
        'sla_breach',
        'room_out_of_service',
        'downtime_hours',
        'escalated',
        'escalation_reason',
        'estimated_cost',
        'actual_cost',
        'closure_notes',
        'customer_satisfaction_score',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'assigned_at' => 'datetime',
            'in_progress_at' => 'datetime',
            'resolved_at' => 'datetime',
            'reopened_at' => 'datetime',
            'sla_breach' => 'boolean',
            'room_out_of_service' => 'boolean',
            'escalated' => 'boolean',
        ];
    }

    public function maintenanceTicket(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTicket::class, 'maintenance_ticket_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }
}
