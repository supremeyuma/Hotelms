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
 * DailyHotelMetricsJob
 *
 * Aggregates daily metrics and caches/stores them for dashboards.
 */
class DailyHotelMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function tags(): array
    {
        return ['reports','daily-metrics'];
    }

    public function handle(ReportService $reports, AuditLoggerService $audit)
    {
        $from = now()->startOfDay()->subDay()->toDateString();
        $to = now()->startOfDay()->toDateString();

        $occupancy = $reports->occupancyReport($from, $to);
        $revenue = $reports->revenueReport($from, $to);
        $orders = $reports->roomServiceVolume($from, $to);

        // Store or cache as needed - for demo store audit log
        $audit->log('daily_metrics_generated', 'Metrics', null, ['occupancy' => $occupancy, 'revenue' => $revenue, 'orders' => $orders]);
    }
}
