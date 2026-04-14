<?php

use App\Jobs\AutoCloseCompletedOrdersJob;
use App\Jobs\AutomaticCheckoutJob;
use App\Jobs\CleanupAuditLogsJob;
use App\Jobs\CleanupOldImagesJob;
use App\Jobs\DailyHotelMetricsJob;
use App\Jobs\DispatchHousekeepingRemindersJob;
use App\Jobs\GenerateOccupancyReportJob;
use App\Jobs\GenerateRevenueReportJob;
use App\Jobs\PurgeOldNotificationsJob;
use App\Jobs\ScheduleMaintenanceRoutineJob;
use App\Jobs\UpdateOrderQueueJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new AutomaticCheckoutJob)->dailyAt('12:00');
Schedule::command('queue:work --once')->everyMinute()->withoutOverlapping();
Schedule::job(new UpdateOrderQueueJob)->everyFiveMinutes();
Schedule::job(new AutoCloseCompletedOrdersJob)->dailyAt('00:30');
Schedule::job(new DispatchHousekeepingRemindersJob)->dailyAt('07:00');
Schedule::job(new ScheduleMaintenanceRoutineJob)->dailyAt('02:00');
Schedule::job(new DailyHotelMetricsJob)->dailyAt('03:00');
Schedule::job(new GenerateRevenueReportJob(now()->subDays(1)->toDateString(), now()->toDateString()))->dailyAt('04:00');
Schedule::job(new GenerateOccupancyReportJob(now()->subDays(7)->toDateString(), now()->toDateString()))->weekly();
Schedule::command('reporting:aggregate')->dailyAt('03:30');
Schedule::command('reporting:detect-exceptions')->hourly();
Schedule::job(new CleanupOldImagesJob)->dailyAt('04:30');
Schedule::job(new CleanupAuditLogsJob)->weekly()->sundays()->at('05:00');
Schedule::job(new PurgeOldNotificationsJob)->dailyAt('05:30');
Schedule::command('billing:charge-rooms')->dailyAt('14:00');
Schedule::command('hotel:night-audit')->dailyAt('02:00');
