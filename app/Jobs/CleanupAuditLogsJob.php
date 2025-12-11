<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * CleanupAuditLogsJob
 *
 * Purges audit logs older than retention period (configurable).
 */
class CleanupAuditLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function tags(): array
    {
        return ['cleanup','audit'];
    }

    public function handle(AuditLoggerService $audit)
    {
        $retainDays = config('hms.retention.audit_logs', 365);
        $threshold = now()->subDays($retainDays);

        $deleted = AuditLog::whereDate('created_at', '<=', $threshold)->delete();

        $audit->log('audit_logs_cleanup', 'AuditLog', null, ['deleted_rows' => $deleted]);
    }
}
