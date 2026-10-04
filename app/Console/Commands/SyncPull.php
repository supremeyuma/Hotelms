<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncPull extends Command
{
    protected $signature = "sync:pull {--node= : Target node ID}";

    protected $description = "Pull sync changes from remote node";

    public function handle(): int
    {
        $this->info("Sync pull initiated...");
        $this->info("No remote configured; skipping.");
        return self::SUCCESS;
    }
}
