<?php

namespace App\Reporting\Projectors;

use App\Models\MaintenanceTicket;
use App\Models\ReportingEvent;
use App\Models\ReportingMaintenanceFact;

class MaintenanceProjector
{
    /**
     * Category used when a ticket carries no issue type in its metadata.
     */
    public const DEFAULT_CATEGORY = 'general';

    /**
     * Project maintenance ticket into fact table.
     *
     * `maintenance_tickets` is a lean operational table: it records the room,
     * the assigned staff, the title, the description, the status and a free-form
     * `meta` bag. The reporting fact is deliberately wider so it can answer
     * SLA and cost questions later on. Anything the ticket does not track is
     * projected as null (or a neutral default where the fact is NOT NULL)
     * rather than read from attributes that do not exist.
     */
    public static function project(MaintenanceTicket $ticket)
    {
        $reportedAt = $ticket->created_at ?? now();

        $fact = ReportingMaintenanceFact::updateOrCreate(
            ['maintenance_ticket_id' => $ticket->id],
            [
                'room_id' => $ticket->room_id,
                'category' => $ticket->issue_type ?: self::DEFAULT_CATEGORY,
                'severity' => 'normal',
                'status' => $ticket->status,
                'reported_at' => $reportedAt,
                'assigned_at' => null,
                'in_progress_at' => null,
                'resolved_at' => null,
                'reopened_at' => null,
                'assigned_staff_id' => $ticket->staff_id,
                'reported_by_id' => null,
                'response_minutes' => null,
                'resolution_minutes' => null,
                'reopen_count' => 0,
                'sla_breach' => false,
                'room_out_of_service' => false,
                'downtime_hours' => null,
                'escalated' => false,
                'escalation_reason' => null,
                'estimated_cost' => null,
                'actual_cost' => null,
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
