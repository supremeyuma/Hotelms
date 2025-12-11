<?php

namespace App\Jobs;

use App\Models\MaintenanceTicket;
use App\Services\MaintenanceService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ScheduleMaintenanceRoutineJob
 *
 * Generates recurring maintenance tickets for periodic checks.
 */
class ScheduleMaintenanceRoutineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function tags(): array
    {
        return ['maintenance','routine'];
    }

    public function handle(MaintenanceService $maintenanceService, AuditLoggerService $audit)
    {
        // Example: create a routine ticket for all rooms every X days if needed
        $rooms = \App\Models\Room::where('status','available')->get();

        foreach ($rooms as $room) {
            // Check last maintenance meta or tickets
            $recent = MaintenanceTicket::where('room_id', $room->id)->where('created_at', '>=', now()->subDays(30))->exists();
            if (! $recent) {
                $ticket = $maintenanceService->createTicket([
                    'room_id' => $room->id,
                    'title' => 'Routine inspection',
                    'description' => 'Scheduled routine check',
                    'priority' => 'low',
                ]);
                $audit->log('maintenance_routine_created', $ticket, $ticket->id);
            }
        }
    }
}
