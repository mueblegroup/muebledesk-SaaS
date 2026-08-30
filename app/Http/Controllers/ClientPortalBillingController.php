<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\SubscriptionPayment;
use App\Services\StripePlatformBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ClientPortalBillingController extends Controller
{
    private const TERMINAL_SUBSCRIPTION_STATUSES = ['canceled', 'incomplete_expired'];

    public function index(Request $request, Company $company, StripePlatformBillingService $stripe): View
    {
        $this->authorizeCompany($request, $company);
        $company->load('subscription.plan', 'subscription.pendingPlan');
        $subscription = $company->subscription;

        if ($subscription?->stripe_subscription_id) {
            try {
                $this->syncFromStripe($subscription, $stripe->retrieveSubscription($subscription->stripe_subscription_id));
                $company->load('subscription.plan', 'subscription.pendingPlan');
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('client-portal.billing', [
            'company' => $company,
            'plans' => PlatformSubscriptionPlan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function checkout(Request $request, Company $company, PlatformSubscriptionPlan $plan, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        abort_unless($plan->is_active, 404);

        try {
            return Cache::lock('platform-subscription-checkout:'.$company->id, 30)->block(5, function () use ($request, $company, $plan, $stripe): RedirectResponse {
                $company->load('subscription.plan', 'subscription.pendingPlan');
                $subscription = $company->subscription;

                if ($subscription?->isActive()) {
                    return $this->changePlan($request, $company, $plan, $stripe);
                }

                if ($this->hasRecoverableStripeSubscription($subscription)) {
                    return back()->with('error', 'An existing Stripe subscription still requires billing attention. Please use Payment settings before purchasing another plan.');
                }

                $autoRenew = $request->boolean('auto_renew', $plan->auto_renew_default);
                $session = $stripe->createCheckoutSession(
                    $company,
                    $plan,
                    $autoRenew,
                    route('client-portal.billing.success', $company),
                    route('client-portal.billing.index', $company)
                );

                return redirect()->away($session['url']);
            });
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function changePlan(Request $request, Company $company, PlatformSubscriptionPlan $plan, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        abort_unless($plan->is_active, 404);

        try {
            return Cache::lock('platform-plan-change:'.$company->id, 45)->block(5, function () use ($company, $plan, $stripe): RedirectResponse {
                $company->load('subscription.plan', 'subscription.pendingPlan');
                $subscription = $company->subscription;

                if (! $subscription?->isActive() || ! $subscription->stripe_subscription_id || ! $subscription->plan) {
                    return back()->with('error', 'An active Stripe subscription is required before changing plans.');
                }

                if ($subscription->pending_platform_subscription_plan_id) {
                    return back()->with('error', 'A plan change is already pending. Cancel it before choosing another plan.');
                }

                if ((int) $subscription->platform_subscription_plan_id === (int) $plan->id) {
                    return back()->with('info', 'This is already your current plan.');
                }

                if (strtoupper((string) $subscription->plan->currency) !== strtoupper((string) $plan->currency)) {
                    return back()->with('error', 'Plan changes cannot switch billing currency on an existing subscription.');
                }

                $direction = $plan->tierDirectionComparedTo($subscription->plan);
                if ($direction === null || $direction === 0) {
                    return back()->with('error', 'These plans do not have an unambiguous upgrade/downgrade order. Set Billing rank in Superadmin before allowing this change.');
                }

                if (! $plan->sameBillingIntervalAs($subscription->plan)) {
                    return back()->with('error', 'Changing billing intervals on an active subscription is currently disabled for safety. Cancel at period end, then purchase the new billing interval after this paid period finishes.');
                }

                if ($direction > 0) {
                    $result = $stripe->upgradeSubscription($subscription, $plan);

                    if (! ($result['applied'] ?? false)) {
                        $subscription->update([
                            'pending_platform_subscription_plan_id' => $plan->id,
                            'pending_plan_effective_at' => null,
                        ]);

                        if (! empty($result['invoice_url'])) {
                            return redirect()->away($result['invoice_url']);
                        }

                        return back()->with('warning', 'Stripe created the prorated upgrade invoice, but payment is still required. Your current plan remains active until payment succeeds.');
                    }

                    $this->syncFromStripe($subscription, $result['subscription'], $plan);
                    return back()->with('success', 'Upgrade completed. Stripe confirmed the higher recurring price and the upgraded plan is active now.');
                }

                if (! $subscription->auto_renew) {
                    return back()->with('error', 'This subscription is already set to end at the current period. Resume it before choosing a lower recurring plan.');
                }

                $result = $stripe->deferDowngrade($subscription, $plan);
                $subscription->update([
                    'pending_platform_subscription_plan_id' => $plan->id,
                    'pending_plan_effective_at' => now()->setTimestamp((int) $result['effective_at']),
                    'stripe_subscription_schedule_id' => null,
                ]);

                return back()->with('success', 'Downgrade saved in Stripe. There is no immediate charge or refund; your current plan remains available until the next renewal, when the lower plan takes effect.');
            });
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function cancelPendingPlanChange(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        $company->load('subscription.plan', 'subscription.pendingPlan');
        $subscription = $company->subscription;

        if (! $subscription?->stripe_subscription_id || ! $subscription->pending_platform_subscription_plan_id) {
            return back()->with('info', 'There is no pending plan change to cancel.');
        }

        try {
            Cache::lock('platform-plan-change:'.$company->id, 45)->block(5, function () use ($subscription, $stripe): void {
                $subscription->refresh()->load('plan');
                $stripe->cancelDeferredPlanChange($subscription);
                $subscription->update([
                    'pending_platform_subscription_plan_id' => null,
                    'pending_plan_effective_at' => null,
                    'stripe_subscription_schedule_id' => null,
                ]);
            });

            return back()->with('success', 'Pending plan change canceled. Stripe was restored to the current plan price.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function clearLegacySchedule(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        $company->load('subscription');
        $subscription = $company->subscription;

        if (! $subscription?->stripe_subscription_id) {
            return back()->with('info', 'There is no Stripe subscription to inspect.');
        }

        try {
            $released = $stripe->cancelScheduledPlanChange($subscription);
            $subscription->update(['stripe_subscription_schedule_id' => null]);

            return $released
                ? back()->with('success', 'Legacy Stripe schedule cleared. The subscription itself remains active.')
                : back()->with('info', 'No legacy Stripe schedule is attached.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function setSubscriptionCancellation(Request $request, Company $company, StripePlatformBillingService $stripe, bool $cancel): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        $company->load('subscription');
        $subscription = $company->subscription;

        if (! $subscription?->stripe_subscription_id) {
            return back()->with('error', 'There is no Stripe subscription to update.');
        }

        if ($cancel && $subscription->pending_platform_subscription_plan_id) {
            return back()->with('error', 'Cancel the pending plan change first, then cancel the subscription.');
        }

        try {
            $remote = Cache::lock('platform-subscription-cancel:'.$company->id, 30)->block(5, fn () => $stripe->setCancelAtPeriodEnd($subscription, $cancel));
            $this->syncFromStripe($subscription, $remote);

            return $cancel
                ? back()->with('success', 'Subscription cancellation scheduled. You keep access through the current paid period and Stripe will not renew it afterward.')
                : back()->with('success', 'Subscription resumed. Automatic renewal is enabled again.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function success(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        $sessionId = (string) $request->query('session_id');

        if ($sessionId !== '') {
            try {
                $session = $stripe->retrieveCheckoutSession($sessionId);

                if (($session['metadata']['purchase_type'] ?? null) === 'extension') {
                    return redirect()->route('client-portal.billing.index', $company)
                        ->with('warning', 'Subscription extensions are no longer supported. Your existing subscription remains unchanged.');
                }

                if (($session['payment_status'] ?? null) === 'paid') {
                    $this->activateFromCheckout($company, $session, $stripe);
                    return redirect()->route('client-portal.billing.index', $company)
                        ->with('success', 'Payment received. Your workspace subscription is now active.');
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('client-portal.billing.index', $company)
            ->with('warning', 'We could not confirm the payment yet. The billing page will reflect it after Stripe synchronization.');
    }

    public function portal(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);

        return match ((string) $request->input('billing_action')) {
            'cancel_plan_change' => $this->cancelPendingPlanChange($request, $company, $stripe),
            'clear_legacy_schedule' => $this->clearLegacySchedule($request, $company, $stripe),
            'cancel_subscription' => $this->setSubscriptionCancellation($request, $company, $stripe, true),
            'resume_subscription' => $this->setSubscriptionCancellation($request, $company, $stripe, false),
            default => $this->openBillingPortal($company, $stripe),
        };
    }

    private function openBillingPortal(Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $company->load('subscription');

        try {
            return redirect()->away($stripe->createBillingPortalSession(
                $company,
                route('client-portal.billing.index', $company)
            )['url']);
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    private function activateFromCheckout(Company $company, array $session, StripePlatformBillingService $stripe): void
    {
        $plan = PlatformSubscriptionPlan::findOrFail((int) ($session['metadata']['plan_id'] ?? 0));
        $this->validateSubscriptionCheckout($company, $plan, $session);
        $subscriptionValue = $session['subscription'] ?? null;

        if (is_array($subscriptionValue)) {
            $subscriptionId = (string) ($subscriptionValue['id'] ?? '');
            $subscriptionData = $subscriptionValue;
        } else {
            $subscriptionId = (string) ($subscriptionValue ?? '');
            $subscriptionData = $subscriptionId !== '' ? $stripe->retrieveSubscription($subscriptionId) : [];
        }

        if ($subscriptionId === '') {
            throw new RuntimeException('Stripe did not return a subscription ID for this paid Checkout Session.');
        }

        $startsAt = isset($subscriptionData['current_period_start']) ? now()->setTimestamp($subscriptionData['current_period_start']) : now();
        $remoteEnd = isset($subscriptionData['current_period_end']) ? now()->setTimestamp($subscriptionData['current_period_end']) : null;
        $expiresAt = $remoteEnd ?? $plan->calculateExpiry($startsAt);

        $subscription = CompanySubscription::updateOrCreate(
            ['company_id' => $company->id],
            [
                'platform_subscription_plan_id' => $plan->id,
                'pending_platform_subscription_plan_id' => null,
                'pending_plan_effective_at' => null,
                'status' => (string) ($subscriptionData['status'] ?? 'active'),
                'is_enabled' => true,
                'auto_renew' => ! (bool) ($subscriptionData['cancel_at_period_end'] ?? false),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'current_period_starts_at' => $startsAt,
                'current_period_ends_at' => $remoteEnd,
                'stripe_customer_id' => $this->stripeId($session['customer'] ?? null),
                'stripe_subscription_id' => $subscriptionId,
                'stripe_subscription_schedule_id' => null,
                'stripe_checkout_session_id' => $session['id'] ?? null,
                'renewal_failure_count' => 0,
                'last_renewal_error' => null,
            ]
        );

        SubscriptionPayment::updateOrCreate(
            ['provider' => 'stripe', 'provider_invoice_id' => (string) ($session['id'] ?? '')],
            [
                'company_id' => $company->id,
                'company_subscription_id' => $subscription->id,
                'platform_subscription_plan_id' => $plan->id,
                'provider_payment_id' => $this->stripeId($session['payment_intent'] ?? null),
                'provider_customer_id' => $this->stripeId($session['customer'] ?? null),
                'status' => 'paid',
                'amount' => ((int) ($session['amount_total'] ?? 0)) / 100,
                'currency' => strtoupper((string) ($session['currency'] ?? $plan->currency)),
                'description' => $plan->name.' subscription',
                'paid_at' => now(),
                'failed_at' => null,
                'failure_message' => null,
                'metadata' => ['checkout_session_id' => $session['id'] ?? null],
            ]
        );
    }

    private function validateSubscriptionCheckout(Company $company, PlatformSubscriptionPlan $plan, array $session): void
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            throw new RuntimeException('Stripe has not confirmed this subscription payment as paid.');
        }

        if (($session['metadata']['purchase_type'] ?? null) !== 'subscription') {
            throw new RuntimeException('Stripe Checkout Session is not a subscription purchase.');
        }

        if ((int) ($session['metadata']['company_id'] ?? 0) !== (int) $company->id
            || (int) ($session['client_reference_id'] ?? 0) !== (int) $company->id) {
            throw new RuntimeException('Stripe subscription payment does not belong to this company.');
        }

        if ((int) ($session['metadata']['plan_id'] ?? 0) !== (int) $plan->id) {
            throw new RuntimeException('Stripe subscription payment does not match the selected plan.');
        }

        $expectedAmount = (int) round(((float) $plan->price) * 100);
        $actualAmount = (int) ($session['amount_total'] ?? -1);
        if ($expectedAmount < 0 || $actualAmount !== $expectedAmount) {
            throw new RuntimeException('Stripe subscription payment amount does not match the plan price.');
        }

        $expectedCurrency = strtolower((string) $plan->currency);
        $actualCurrency = strtolower((string) ($session['currency'] ?? ''));
        if ($actualCurrency === '' || $actualCurrency !== $expectedCurrency) {
            throw new RuntimeException('Stripe subscription payment currency does not match the plan currency.');
        }
    }

    private function hasRecoverableStripeSubscription(?CompanySubscription $subscription): bool
    {
        if (! $subscription?->stripe_subscription_id) {
            return false;
        }

        return ! in_array((string) $subscription->status, self::TERMINAL_SUBSCRIPTION_STATUSES, true);
    }

    private function syncFromStripe(CompanySubscription $record, array $subscription, ?PlatformSubscriptionPlan $knownPlan = null): void
    {
        $status = (string) ($subscription['status'] ?? $record->status);
        $start = isset($subscription['current_period_start']) ? now()->setTimestamp($subscription['current_period_start']) : $record->starts_at;
        $remoteEnd = isset($subscription['current_period_end']) ? now()->setTimestamp($subscription['current_period_end']) : null;
        $priceId = $this->subscriptionPriceId($subscription);
        $resolvedPlan = $knownPlan
            ?? ($priceId !== '' ? PlatformSubscriptionPlan::where('stripe_price_id', $priceId)->first() : null)
            ?? PlatformSubscriptionPlan::find((int) ($subscription['metadata']['plan_id'] ?? 0));
        $pendingPlanId = (int) ($record->pending_platform_subscription_plan_id ?? 0);
        $pendingEffectiveAt = $record->pending_plan_effective_at;
        $hasRemotePendingUpdate = ! empty($subscription['pending_update']);
        $remoteScheduleId = $this->stripeId($subscription['schedule'] ?? null);
        $deferredPlanStillPending = $pendingPlanId > 0
            && $pendingEffectiveAt
            && $pendingEffectiveAt->isFuture()
            && $resolvedPlan
            && (int) $resolvedPlan->id === $pendingPlanId;

        $updates = [
            'status' => $status,
            'stripe_customer_id' => $this->stripeId($subscription['customer'] ?? null) ?: $record->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'stripe_subscription_schedule_id' => $remoteScheduleId,
            'starts_at' => $start,
            'expires_at' => $remoteEnd ?? $record->expires_at,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $remoteEnd ?? $record->current_period_ends_at,
            'auto_renew' => ! (bool) ($subscription['cancel_at_period_end'] ?? false),
            'is_enabled' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired', 'paused'], true),
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ];

        if ($resolvedPlan && ! $deferredPlanStillPending) {
            $updates['platform_subscription_plan_id'] = $resolvedPlan->id;
        }

        if ($pendingPlanId > 0 && $resolvedPlan && (int) $resolvedPlan->id === $pendingPlanId && ! $deferredPlanStillPending) {
            $updates['pending_platform_subscription_plan_id'] = null;
            $updates['pending_plan_effective_at'] = null;
        } elseif ($pendingPlanId > 0 && ! $pendingEffectiveAt && ! $hasRemotePendingUpdate) {
            $updates['pending_platform_subscription_plan_id'] = null;
        }

        $record->update($updates);
    }

    private function subscriptionPriceId(array $subscription): string
    {
        $price = data_get($subscription, 'items.data.0.price');
        return is_array($price) ? (string) ($price['id'] ?? '') : (string) ($price ?? '');
    }

    private function stripeId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }

        $value = is_scalar($value) ? (string) $value : '';
        return $value !== '' ? $value : null;
    }

    private function authorizeCompany(Request $request, Company $company, bool $ownerOnly = false): void
    {
        $membership = $request->user()->companies()->whereKey($company->id)->first();
        abort_unless($membership, 403);

        if ($ownerOnly) {
            abort_unless($membership->pivot->role === 'owner', 403);
            return;
        }

        abort_unless(in_array($membership->pivot->role, ['owner', 'admin'], true), 403);
    }
}
