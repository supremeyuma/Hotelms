<?php

namespace App\Listeners;

use App\Events\MaintenanceReported;
use App\Jobs\SendMaintenanceAlertJob;

class DispatchMaintenanceAlert
{
    public function handle(MaintenanceReported $event): void
    {
        SendMaintenanceAlertJob::dispatch($event->ticket);
    }
}
