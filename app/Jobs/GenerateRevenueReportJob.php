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
 * GenerateRevenueReportJob
 *
 * Generates a revenue report for a date range and stores as CSV or DB export.
 */
class GenerateRevenueReportJob implements ShouldQueue
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
        return ['reports','revenue'];
    }

    public function handle(ReportService $reportService, AuditLoggerService $audit)
    {
        $report = $reportService->revenueReport($this->from, $this->to);
        // Persist or generate file - simplified: log summary
        $audit->log('revenue_report_generated', 'Report', null, ['from' => $this->from, 'to' => $this->to, 'summary' => $report['total_revenue'] ?? 0]);
    }
}
