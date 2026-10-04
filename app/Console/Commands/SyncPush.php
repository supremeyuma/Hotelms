<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncPush extends Command
{
    protected $signature = "sync:push {--node= : Target node ID}";

    protected $description = "Push sync changes to remote node";

    public function handle(): int
    {
        $this->info("Sync push initiated...");
        $this->info("No remote configured; skipping.");
        return self::SUCCESS;
    }
}
