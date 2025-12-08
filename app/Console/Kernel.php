<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

// Queue Jobs
use App\Jobs\ProcessOrdersJob;
use App\Jobs\SendNotificationJob;
use App\Jobs\UpdateInventoryJob;
use App\Jobs\CompleteTaskJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        /**
         * ---------------------------------------------------------
         * QUEUE PROCESSING (Hostinger Cron Compatible)
         * ---------------------------------------------------------
         * Use --once because Hostinger cron cannot run daemons.
         * Cron will trigger queue:work once every minute.
         */
        $schedule->command('queue:work --once')->everyMinute();


        /**
         * ---------------------------------------------------------
         * ORDER PROCESSING JOB
         * ---------------------------------------------------------
         * Run every 5 minutes:
         *   - Picks unprocessed orders
         *   - Charges payments (if applicable)
         *   - Updates order status
         */
        $schedule->job(new ProcessOrdersJob)->everyFiveMinutes();


        /**
         * ---------------------------------------------------------
         * SEND NOTIFICATIONS (Email/SMS)
         * ---------------------------------------------------------
         * Runs every minute to keep notifications responsive.
         */
        $schedule->job(new SendNotificationJob)->everyMinute();


        /**
         * ---------------------------------------------------------
         * INVENTORY UPDATE JOB
         * ---------------------------------------------------------
         * Runs every 10 minutes to:
         *   - Sync stock levels
         *   - Update low-stock alerts
         */
        $schedule->job(new UpdateInventoryJob)->everyTenMinutes();


        /**
         * ---------------------------------------------------------
         * AUTO-COMPLETE TASKS
         * ---------------------------------------------------------
         * Example: auto-complete trainer sessions,
         * maintenance tasks, subscriptions, etc.
         */
        $schedule->job(new CompleteTaskJob)->everyThirtyMinutes();


        /**
         * ---------------------------------------------------------
         * CUSTOM COMMANDS
         * ---------------------------------------------------------
         * Example: order synchronization command
         */
        $schedule->command('orders:sync')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
