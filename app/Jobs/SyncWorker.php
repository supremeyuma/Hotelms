<?php

namespace App\Jobs;

use App\Sync\SyncCycleRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(SyncCycleRunner $runner): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        $result = $runner->run();

        Log::info('Sync cycle complete.', $result);
    }
}
