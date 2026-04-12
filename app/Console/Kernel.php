<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\UpdateOrderQueueJob;
use App\Jobs\AutoCloseCompletedOrdersJob;
use App\Jobs\DispatchHousekeepingRemindersJob;
use App\Jobs\ScheduleMaintenanceRoutineJob;
use App\Jobs\DailyHotelMetricsJob;
use App\Jobs\CleanupOldImagesJob;
use App\Jobs\CleanupAuditLogsJob;
use App\Jobs\PurgeOldNotificationsJob;
use App\Jobs\GenerateRevenueReportJob;
use App\Jobs\GenerateOccupancyReportJob;
use App\Jobs\AutomaticCheckoutJob;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Auto-checkout guests at midday on their checkout date
        $schedule->job(new AutomaticCheckoutJob())->dailyAt('12:00');

        // Run schedule every minute to process queue:work --once (Hostinger compatible)
        $schedule->command('queue:work --once')->everyMinute()->withoutOverlapping();

        // Update order queue frequently
        $schedule->job(new UpdateOrderQueueJob())->everyFiveMinutes();

        // Auto close delivered orders daily midnight
        $schedule->job(new AutoCloseCompletedOrdersJob())->dailyAt('00:30');

        // Housekeeping reminders once every morning at 07:00
        $schedule->job(new DispatchHousekeepingRemindersJob())->dailyAt('07:00');

        // Routine maintenance scheduling once daily
        $schedule->job(new ScheduleMaintenanceRoutineJob())->dailyAt('02:00');

        // Generate daily metrics early morning
        $schedule->job(new DailyHotelMetricsJob())->dailyAt('03:00');

        // Reports (example: weekly and daily)
        $schedule->job(new GenerateRevenueReportJob(now()->subDays(1)->toDateString(), now()->toDateString()))->dailyAt('04:00');
        $schedule->job(new GenerateOccupancyReportJob(now()->subDays(7)->toDateString(), now()->toDateString()))->weekly();

        // Reporting aggregation and exception detection
        $schedule->command('reporting:aggregate')->dailyAt('03:30');  // Aggregate yesterday's facts
        $schedule->command('reporting:detect-exceptions')->hourly();  // Check for exceptions hourly

        // Cleanup tasks
        $schedule->job(new CleanupOldImagesJob())->dailyAt('04:30');
        $schedule->job(new CleanupAuditLogsJob())->weekly()->sundays()->at('05:00');
        $schedule->job(new PurgeOldNotificationsJob())->dailyAt('05:30');

        $schedule->command('billing:charge-rooms')->dailyAt('14:00');

        $schedule->command('hotel:night-audit')->dailyAt('02:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
