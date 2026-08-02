<?php

namespace App\Console\Commands;

use App\Models\CompanySubscription;
use Illuminate\Console\Command;

class ProcessCompanySubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';
    protected $description = 'Expire ended subscriptions and extend eligible manual auto-renew subscriptions.';

    public function handle(): int
    {
        $processed = 0;

        CompanySubscription::with('plan')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($subscriptions) use (&$processed): void {
                foreach ($subscriptions as $subscription) {
                    $processed++;

                    if (! $subscription->is_enabled) {
                        $subscription->update(['status' => 'disabled']);
                        continue;
                    }

                    if ($subscription->stripe_subscription_id) {
                        if (in_array($subscription->status, ['past_due', 'unpaid', 'canceled'], true) || ! $subscription->auto_renew) {
                            $subscription->update(['status' => 'expired']);
                        }
                        continue;
                    }

                    if ($subscription->auto_renew && $subscription->plan) {
                        $subscription->update([
                            'status' => 'active',
                            'starts_at' => $subscription->expires_at,
                            'expires_at' => $subscription->plan->calculateExpiry($subscription->expires_at),
                            'last_renewal_attempt_at' => now(),
                            'renewal_failure_count' => 0,
                            'last_renewal_error' => null,
                        ]);
                    } else {
                        $subscription->update(['status' => 'expired']);
                    }
                }
            });

        $this->info("Processed {$processed} subscription(s).");

        return self::SUCCESS;
    }
}
