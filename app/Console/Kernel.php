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
        // Run hourly so each tenant can be evaluated shortly after midnight in
        // its own configured timezone. The command itself performs the final
        // per-company local-date check and remains idempotent via next_invoice_date.
        $schedule->command(GenerateRecurringInvoices::class)->hourly()->withoutOverlapping();
        $schedule->command(ProcessCompanySubscriptionRenewals::class)->hourly()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
