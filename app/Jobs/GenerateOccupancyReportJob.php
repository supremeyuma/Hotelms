<?php

namespace App\Jobs;

use App\Services\ReportService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * GenerateOccupancyReportJob
 */
class GenerateOccupancyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $from;
    public string $to;
    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function tags(): array
    {
        return ['reports','occupancy'];
    }

    public function handle(ReportService $reportService, AuditLoggerService $audit)
    {
        $report = $reportService->occupancyReport($this->from, $this->to);
        $audit->log('occupancy_report_generated', 'Report', null, ['from' => $this->from, 'to' => $this->to, 'occupancy' => $report['occupancy_percent'] ?? 0]);
    }
}
