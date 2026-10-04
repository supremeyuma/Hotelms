<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncRun extends Command
{
    protected $signature = "sync:run";

    protected $description = "Run bidirectional sync";

    public function handle(): int
    {
        $this->call("sync:pull");
        $this->call("sync:push");
        return self::SUCCESS;
    }
}
