<?php

namespace App\Reporting\Projectors;

use App\Models\MaintenanceTicket;
use App\Models\ReportingEvent;
use App\Models\ReportingMaintenanceFact;

class MaintenanceProjector
{
    /**
     * Project maintenance ticket into fact table
     */
    public static function project(MaintenanceTicket $ticket)
    {
        $responseMinutes = $ticket->assigned_at
            ? $ticket->reported_at->diffInMinutes($ticket->assigned_at)
            : null;

        $resolutionMinutes = $ticket->resolved_at
            ? $ticket->reported_at->diffInMinutes($ticket->resolved_at)
            : null;

        $slaThreshold = config('reporting.maintenance_sla_hours', 24) * 60;

        $fact = ReportingMaintenanceFact::updateOrCreate(
            ['maintenance_ticket_id' => $ticket->id],
            [
                'room_id' => $ticket->room_id,
                'category' => $ticket->category,
                'severity' => $ticket->priority ?? 'normal',
                'status' => $ticket->status,
                'reported_at' => $ticket->reported_at,
                'assigned_at' => $ticket->assigned_at,
                'in_progress_at' => $ticket->in_progress_at,
                'resolved_at' => $ticket->resolved_at,
                'reopened_at' => $ticket->reopened_at,
                'assigned_staff_id' => $ticket->assigned_to_id,
                'reported_by_id' => $ticket->reported_by_id,
                'response_minutes' => $responseMinutes,
                'resolution_minutes' => $resolutionMinutes,
                'sla_breach' => $resolutionMinutes && $resolutionMinutes > $slaThreshold,
                'room_out_of_service' => $ticket->room_out_of_service ?? false,
                'downtime_hours' => $ticket->downtime_hours,
                'escalated' => $ticket->escalated ?? false,
                'escalation_reason' => $ticket->escalation_reason,
                'estimated_cost' => $ticket->estimated_cost,
                'actual_cost' => $ticket->actual_cost,
            ]
        );

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'maintenance.projected',
            'domain' => 'operations',
            'department' => 'maintenance',
            'room_id' => $ticket->room_id,
            'reference_type' => 'MaintenanceTicket',
            'reference_id' => $ticket->id,
        ]);

        return $fact;
    }

    /**
     * Project on status change
     */
    public static function projectOnStatusChange(MaintenanceTicket $ticket, $oldStatus)
    {
        self::project($ticket);

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'maintenance.status_changed',
            'domain' => 'operations',
            'department' => 'maintenance',
            'room_id' => $ticket->room_id,
            'status_before' => $oldStatus,
            'status_after' => $ticket->status,
            'reference_type' => 'MaintenanceTicket',
            'reference_id' => $ticket->id,
        ]);
    }
}
