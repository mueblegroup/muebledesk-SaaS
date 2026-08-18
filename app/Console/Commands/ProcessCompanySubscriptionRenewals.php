<?php

namespace App\Console\Commands;

use App\Models\CompanySubscription;
use Illuminate\Console\Command;

class ProcessCompanySubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';
    protected $description = 'Expire ended subscriptions while allowing only free manual plans to renew without a payment provider.';

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

                    // A paid plan without a payment-provider subscription must never
                    // silently extend itself. Superadmins can still extend offline or
                    // complimentary paid subscriptions explicitly from the company page.
                    $isFreePlan = $subscription->plan && (float) $subscription->plan->price <= 0;

                    if ($subscription->auto_renew && $isFreePlan) {
                        $subscription->update([
                            'status' => 'active',
                            'starts_at' => $subscription->expires_at,
                            'expires_at' => $subscription->plan->calculateExpiry($subscription->expires_at),
                            'last_renewal_attempt_at' => now(),
                            'renewal_failure_count' => 0,
                            'last_renewal_error' => null,
                        ]);
                        continue;
                    }

                    $subscription->update([
                        'status' => 'expired',
                        'last_renewal_attempt_at' => now(),
                        'last_renewal_error' => $subscription->auto_renew && ! $isFreePlan
                            ? 'Automatic renewal was not performed because this paid subscription has no payment-provider subscription.'
                            : null,
                    ]);
                }
            });

        $this->info("Processed {$processed} subscription(s).");

        return self::SUCCESS;
    }
}
