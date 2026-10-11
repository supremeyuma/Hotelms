<?php

namespace App\Console\Commands;

use App\Sync\SyncCycleRunner;
use Illuminate\Console\Command;

class SyncRun extends Command
{
    protected $signature = 'sync:run {--node= : Limit the cycle to one peer}';

    protected $description = 'Run one synchronization cycle against every configured peer';

    public function handle(SyncCycleRunner $runner): int
    {
        if (! config('sync.enabled')) {
            $this->warn('Sync is disabled. Set SYNC_ENABLED=true to enable it.');

            return self::SUCCESS;
        }

        $result = $runner->run($this->option('node') ?: null);

        $this->info(sprintf(
            'Peers: %d, pushed: %d, pulled: %d, failed: %d',
            $result['peers'],
            $result['pushed'],
            $result['pulled'],
            count($result['failed']),
        ));

        foreach ($result['failed'] as $peer) {
            $this->warn("  unreachable: {$peer}");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
