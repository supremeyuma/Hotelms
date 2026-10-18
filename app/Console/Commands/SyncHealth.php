<?php

namespace App\Console\Commands;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncConflict;
use App\Models\Sync\SyncCursor;
use App\Models\Sync\SyncNode;
use Illuminate\Console\Command;

class SyncHealth extends Command
{
    protected $signature = 'sync:health';

    protected $description = 'Report sync health for this node: nodes, changes, conflicts and cursors';

    public function handle(): int
    {
        $changes = SyncChange::count();

        // A stale change count is the operator's signal that something is
        // wrong before a guest notices.
        $stale = SyncChange::where('created_at', '<', now()->subHours(24))->count();
        $conflicts = SyncConflict::whereNull('resolved_at')->count();
        $active = SyncNode::where('status', 'active')->count();
        $inactive = SyncNode::where('status', '!=', 'active')->count();

        $this->table(['Metric', 'Value'], [
            ['active nodes',           (string) $active],
            ['inactive nodes',         (string) $inactive],
            ['changes recorded',       (string) $changes],
            ['changes older than 24h', (string) $stale],
            ['unresolved conflicts',   (string) $conflicts],
            ['cursors',                (string) SyncCursor::count()],
        ]);

        if ($conflicts > 0) {
            $this->warn("{$conflicts} unresolved conflict(s) require a human. Inspect the sync_conflicts table (php artisan tinker).");
        }

        if ($inactive > 0) {
            $this->warn("{$inactive} node(s) are not active. Check last_seen_at before assuming a terminal is offline.");
        }

        return self::SUCCESS;
    }
}
