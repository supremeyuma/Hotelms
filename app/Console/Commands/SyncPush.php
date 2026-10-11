<?php

namespace App\Console\Commands;

use App\Sync\SyncCycleRunner;
use Illuminate\Console\Command;

class SyncPush extends Command
{
    protected $signature = 'sync:push {--node= : Limit the push to one peer}';

    protected $description = 'Push local changes to the configured peers';

    public function handle(SyncCycleRunner $runner): int
    {
        if (! config('sync.enabled')) {
            $this->warn('Sync is disabled. Set SYNC_ENABLED=true to enable it.');

            return self::SUCCESS;
        }

        $result = $runner->push($this->option('node') ?: null);

        $this->info(sprintf(
            'Peers: %d, pushed: %d, failed: %d',
            $result['peers'],
            $result['pushed'],
            count($result['failed']),
        ));

        foreach ($result['failed'] as $peer) {
            $this->warn("  unreachable: {$peer}");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
