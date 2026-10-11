<?php

namespace App\Console\Commands;

use App\Sync\SyncCycleRunner;
use Illuminate\Console\Command;

class SyncPull extends Command
{
    protected $signature = 'sync:pull {--node= : Limit the pull to one peer}';

    protected $description = 'Pull and apply changes from the configured peers';

    public function handle(SyncCycleRunner $runner): int
    {
        if (! config('sync.enabled')) {
            $this->warn('Sync is disabled. Set SYNC_ENABLED=true to enable it.');

            return self::SUCCESS;
        }

        $result = $runner->pull($this->option('node') ?: null);

        $this->info(sprintf(
            'Peers: %d, pulled: %d, failed: %d',
            $result['peers'],
            $result['pulled'],
            count($result['failed']),
        ));

        foreach ($result['failed'] as $peer) {
            $this->warn("  unreachable: {$peer}");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
