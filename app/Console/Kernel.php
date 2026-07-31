<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\GenerateRecurringInvoices; // <--- Add this line

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run the recurring invoice generation daily at a specific time (e.g., 1:00 AM)
        // $schedule->command(GenerateRecurringInvoices::class)->everyMinute()->withoutOverlapping();

        $schedule->command(GenerateRecurringInvoices::class)->dailyAt('01:00')->withoutOverlapping();
        // You can also run it hourly: $schedule->command(GenerateRecurringInvoices::class)->hourly();
        // Or every five minutes for testing: $schedule->command(GenerateRecurringInvoices::class)->everyFiveMinutes();
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