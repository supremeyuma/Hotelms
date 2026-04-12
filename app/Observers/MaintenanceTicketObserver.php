<?php

namespace App\Observers;

use App\Models\MaintenanceTicket;
use App\Reporting\Projectors\MaintenanceProjector;

class MaintenanceTicketObserver
{
    /**
     * Handle the MaintenanceTicket "created" event.
     */
    public function created(MaintenanceTicket $ticket)
    {
        // Project new ticket into reporting layer
        MaintenanceProjector::project($ticket);
    }

    /**
     * Handle the MaintenanceTicket "updated" event.
     */
    public function updated(MaintenanceTicket $ticket)
    {
        // Track status changes (open → assigned → in_progress → resolved)
        if ($ticket->isDirty('status')) {
            MaintenanceProjector::projectOnStatusChange($ticket, $ticket->getOriginal('status'));
        }

        // Track assignment changes
        if ($ticket->isDirty('assigned_to_id')) {
            MaintenanceProjector::project($ticket);
        }

        // Track resolution timestamp
        if ($ticket->isDirty('resolved_at')) {
            MaintenanceProjector::project($ticket);
        }

        // Project all updates
        MaintenanceProjector::project($ticket);
    }

    /**
     * Handle the MaintenanceTicket "deleted" event.
     */
    public function deleted(MaintenanceTicket $ticket)
    {
        // Note: Deletion tracking can be added here if needed
    }
}
