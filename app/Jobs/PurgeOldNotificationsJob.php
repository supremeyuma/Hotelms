<?php

namespace App\Jobs;

use Illuminate\Notifications\DatabaseNotification;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * PurgeOldNotificationsJob
 */
class PurgeOldNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function tags(): array
    {
        return ['cleanup','notifications'];
    }

    public function handle(AuditLoggerService $audit)
    {
        $retainDays = config('hms.retention.notifications', 90);
        $threshold = now()->subDays($retainDays);
        $count = DatabaseNotification::where('created_at', '<=', $threshold)->delete();
        $audit->log('notifications_purged', 'Notification', null, ['deleted' => $count]);
    }
}
