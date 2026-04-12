<?php

namespace App\Console\Commands;

use App\Reporting\Exceptions\ExceptionDetector;
use Illuminate\Console\Command;

class DetectReportingExceptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reporting:detect-exceptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run exception detection to identify exceptions that require attention';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Running exception detection...');

        try {
            ExceptionDetector::runAllDetections();
            $this->info('✓ Exception detection completed successfully');

            return 0;
        } catch (\Exception $e) {
            $this->error('Exception detection failed: '.$e->getMessage());

            return 1;
        }
    }
}
