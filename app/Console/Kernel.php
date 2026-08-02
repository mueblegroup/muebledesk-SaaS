<?php

namespace App\Console;

use App\Console\Commands\GenerateRecurringInvoices;
use App\Console\Commands\ProcessCompanySubscriptionRenewals;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command(GenerateRecurringInvoices::class)->dailyAt('01:00')->withoutOverlapping();
        $schedule->command(ProcessCompanySubscriptionRenewals::class)->hourly()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
