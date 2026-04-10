<?php

namespace App\Jobs;

use App\Models\MaintenanceTicket;
use App\Services\NotificationService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SendMaintenanceAlertJob
 *
 * Triggers alerts to maintenance staff/managers when urgent ticket created.
 */
class SendMaintenanceAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public MaintenanceTicket $ticket;
    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(MaintenanceTicket $ticket)
    {
        $this->ticket = $ticket->withoutRelations();
    }

    public function tags(): array
    {
        return ['maintenance','alert','ticket:'.$this->ticket->id];
    }

    public function handle(NotificationService $notifier, AuditLoggerService $audit)
    {
        $ticket = MaintenanceTicket::with('room','staff')->find($this->ticket->id);
        if (! $ticket) return;

        $priority = $ticket->meta['priority'] ?? 'normal';
        $title = "Maintenance Alert: #{$ticket->id} ({$priority})";
        $payload = ['ticket_id' => $ticket->id, 'room_id' => $ticket->room_id];

        $notifier->notifyDepartment('maintenance', $title, $payload);
        $notifier->notifyManagers($title, $payload);

        $audit->log('maintenance_alert_sent', $ticket, $ticket->id);
    }
}
