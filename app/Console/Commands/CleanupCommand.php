<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CleanupOldImagesJob;
use App\Jobs\CleanupAuditLogsJob;
use App\Jobs\PurgeOldNotificationsJob;

class CleanupCommand extends Command
{
    protected $signature = 'hms:cleanup {--images} {--audit} {--notifications}';
    protected $description = 'Run cleanup jobs for images, audit logs and notifications';

    public function handle()
    {
        if ($this->option('images')) {
            CleanupOldImagesJob::dispatch();
            $this->info('CleanupOldImagesJob dispatched.');
        }

        if ($this->option('audit')) {
            CleanupAuditLogsJob::dispatch();
            $this->info('CleanupAuditLogsJob dispatched.');
        }

        if ($this->option('notifications')) {
            PurgeOldNotificationsJob::dispatch();
            $this->info('PurgeOldNotificationsJob dispatched.');
        }

        if (! $this->option('images') && ! $this->option('audit') && ! $this->option('notifications')) {
            // dispatch all
            CleanupOldImagesJob::dispatch();
            CleanupAuditLogsJob::dispatch();
            PurgeOldNotificationsJob::dispatch();
            $this->info('All cleanup jobs dispatched.');
        }

        return 0;
    }
}
