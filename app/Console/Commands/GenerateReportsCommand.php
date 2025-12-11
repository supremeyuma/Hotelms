<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GenerateRevenueReportJob;
use App\Jobs\GenerateOccupancyReportJob;

class GenerateReportsCommand extends Command
{
    protected $signature = 'hms:generate-reports {--from=} {--to=}';
    protected $description = 'Generate scheduled reports for HHMS';

    public function handle()
    {
        $from = $this->option('from') ?: now()->subDay()->toDateString();
        $to = $this->option('to') ?: now()->toDateString();

        GenerateRevenueReportJob::dispatch($from, $to);
        GenerateOccupancyReportJob::dispatch($from, $to);

        $this->info("Report jobs dispatched for {$from} to {$to}");
        return 0;
    }
}
