<?php

namespace App\Observers;

use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Services\BillingActivityNotifier;

class CompanySubscriptionNotificationObserver
{
    public function updated(CompanySubscription $subscription): void
    {
        $subscription->loadMissing('company', 'plan', 'pendingPlan');
        $company = $subscription->company;
        if (! $company) {
            return;
        }

        if ($subscription->wasChanged('platform_subscription_plan_id')
            && in_array((string) $subscription->status, ['active', 'trialing'], true)) {
            $oldPlan = PlatformSubscriptionPlan::find((int) $subscription->getOriginal('platform_subscription_plan_id'));
            $newPlan = $subscription->plan;

            if ($newPlan && (! $oldPlan || (int) $oldPlan->id !== (int) $newPlan->id)) {
                app(BillingActivityNotifier::class)->notifyOwners(
                    $company,
                    'Subscription plan changed — '.$company->name,
                    'Your MuebleDesk subscription plan has changed.',
                    [
                        'Previous plan' => $oldPlan?->name ?? 'None',
                        'New plan' => $newPlan->name,
                        'New plan price' => strtoupper((string) $newPlan->currency).' '.number_format((float) $newPlan->price, 2),
                        'Effective' => 'Now',
                    ]
                );
            }
        }

        if ($subscription->wasChanged('pending_platform_subscription_plan_id')
            && $subscription->pending_platform_subscription_plan_id
            && $subscription->pending_plan_effective_at) {
            $pendingPlan = $subscription->pendingPlan;

            if ($pendingPlan) {
                app(BillingActivityNotifier::class)->notifyOwners(
                    $company,
                    'Plan change scheduled — '.$company->name,
                    'A future subscription plan change has been saved for your MuebleDesk workspace.',
                    [
                        'Current plan' => $subscription->plan?->name ?? 'Current plan',
                        'Next plan' => $pendingPlan->name,
                        'Next price' => strtoupper((string) $pendingPlan->currency).' '.number_format((float) $pendingPlan->price, 2),
                        'Effective at' => $subscription->pending_plan_effective_at->timezone($company->timezone ?: config('app.timezone'))->format('d M Y H:i T'),
                    ]
                );
            }
        }

        $oldPendingPlanId = (int) ($subscription->getOriginal('pending_platform_subscription_plan_id') ?? 0);
        if ($subscription->wasChanged('pending_platform_subscription_plan_id')
            && $oldPendingPlanId > 0
            && ! $subscription->pending_platform_subscription_plan_id
            && ! $subscription->wasChanged('platform_subscription_plan_id')) {
            $oldPendingPlan = PlatformSubscriptionPlan::find($oldPendingPlanId);

            app(BillingActivityNotifier::class)->notifyOwners(
                $company,
                'Pending plan change canceled — '.$company->name,
                'The pending subscription plan change was canceled and your current plan will continue.',
                [
                    'Current plan' => $subscription->plan?->name ?? 'Current plan',
                    'Canceled change' => $oldPendingPlan?->name,
                ]
            );
        }

        if ($subscription->wasChanged('auto_renew') && in_array((string) $subscription->status, ['active', 'trialing', 'past_due'], true)) {
            $autoRenew = (bool) $subscription->auto_renew;
            $dateLabel = $autoRenew ? 'Next renewal' : 'Access through';
            $dateValue = ($subscription->current_period_ends_at ?? $subscription->expires_at)
                ?->timezone($company->timezone ?: config('app.timezone'))
                ->format('d M Y H:i T');

            app(BillingActivityNotifier::class)->notifyOwners(
                $company,
                $autoRenew ? 'Subscription resumed — '.$company->name : 'Subscription cancellation scheduled — '.$company->name,
                $autoRenew
                    ? 'Automatic renewal has been re-enabled for your MuebleDesk subscription.'
                    : 'Your MuebleDesk subscription is set to end after the current paid period. No further automatic renewal will occur unless you resume it.',
                [
                    'Plan' => $subscription->plan?->name ?? 'Subscription plan',
                    $dateLabel => $dateValue,
                ]
            );
        }

        if ($subscription->wasChanged('status')) {
            $oldStatus = (string) $subscription->getOriginal('status');
            $newStatus = (string) $subscription->status;

            if (in_array($newStatus, ['canceled', 'unpaid', 'paused'], true) && $oldStatus !== $newStatus) {
                app(BillingActivityNotifier::class)->notifyOwners(
                    $company,
                    'Subscription status changed — '.$company->name,
                    'Your MuebleDesk subscription status has changed and may affect workspace access or future billing.',
                    [
                        'Plan' => $subscription->plan?->name ?? 'Subscription plan',
                        'Previous status' => ucfirst(str_replace('_', ' ', $oldStatus)),
                        'New status' => ucfirst(str_replace('_', ' ', $newStatus)),
                    ]
                );
            }
        }
    }
}
