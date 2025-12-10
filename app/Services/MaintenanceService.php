<?php
// app/Services/MaintenanceService.php

namespace App\Services;

use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Services\AuditLoggerService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * MaintenanceService
 *
 * Handles maintenance ticket lifecycle and notifications.
 */
class MaintenanceService
{
    protected AuditLoggerService $audit;
    protected NotificationService $notifier;

    public function __construct(AuditLoggerService $audit, NotificationService $notifier)
    {
        $this->audit = $audit;
        $this->notifier = $notifier;
    }

    /**
     * Create a maintenance ticket
     *
     * @param array $data
     * @return MaintenanceTicket
     * @throws Exception
     */
    public function createTicket(array $data)
    {
        return DB::transaction(function () use ($data) {
            $ticket = MaintenanceTicket::create([
                'room_id' => $data['room_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'open',
                'meta' => $data['meta'] ?? null,
            ]);

            // notify maintenance team
            $this->notifier->notifyDepartment('maintenance', "New ticket #{$ticket->id} created", [
                'ticket_id' => $ticket->id,
                'room_id' => $ticket->room_id,
            ]);

            $this->audit->log('maintenance_ticket_created', $ticket, $ticket->id, ['data' => $data]);

            return $ticket;
        });
    }

    /**
     * Assign ticket to staff
     *
     * @param MaintenanceTicket $ticket
     * @param int $staffId
     * @return MaintenanceTicket
     */
    public function assignTicket(MaintenanceTicket $ticket, int $staffId)
    {
        $before = $ticket->toArray();
        $ticket->update(['staff_id' => $staffId, 'status' => 'in_progress']);

        $this->notifier->notifyStaff($staffId, "Assigned ticket #{$ticket->id}", ['ticket_id' => $ticket->id]);
        $this->audit->logChange('maintenance_ticket_assigned', $ticket, $before, $ticket->toArray());

        return $ticket;
    }

    /**
     * Update status and optionally close
     *
     * @param MaintenanceTicket $ticket
     * @param string $status
     * @param array $meta
     * @return MaintenanceTicket
     */
    public function updateStatus(MaintenanceTicket $ticket, string $status, array $meta = [])
    {
        $before = $ticket->toArray();
        $ticket->update(['status' => $status, 'meta' => array_merge($ticket->meta ?? [], $meta)]);
        $this->audit->logChange('maintenance_ticket_status_updated', $ticket, $before, $ticket->toArray());

        if (in_array($status, ['resolved','closed'])) {
            $this->notifier->notifyManagers("Ticket #{$ticket->id} {$status}", ['ticket_id' => $ticket->id]);
        }

        return $ticket;
    }

    /**
     * Close ticket with notes.
     *
     * @param MaintenanceTicket $ticket
     * @param array $data
     * @return MaintenanceTicket
     */
    public function closeTicket(MaintenanceTicket $ticket, array $data = [])
    {
        $before = $ticket->toArray();
        $ticket->update([
            'status' => 'closed',
            'meta' => array_merge($ticket->meta ?? [], ['closed_notes' => $data['notes'] ?? null, 'closed_by' => $data['closed_by'] ?? null]),
        ]);

        $this->audit->logChange('maintenance_ticket_closed', $ticket, $before, $ticket->toArray());
        $this->notifier->notifyManagers("Ticket #{$ticket->id} closed", ['ticket_id' => $ticket->id]);

        return $ticket;
    }
}
