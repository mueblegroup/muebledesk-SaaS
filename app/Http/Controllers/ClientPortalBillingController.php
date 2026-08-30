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
                    return back()->with('error', 'A plan change is already pending. Complete or cancel it before choosing another plan.');
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

                $immediateUpgrade = $direction > 0 && $plan->sameBillingIntervalAs($subscription->plan);

                if ($immediateUpgrade) {
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
                    return back()->with('success', 'Upgrade completed. Stripe charged the prorated difference and the higher plan is active now.');
                }

                if (! $subscription->auto_renew) {
                    return back()->with('error', 'Auto-renewal is disabled. Re-enable it before scheduling a different plan for the next billing period.');
                }

                $result = $stripe->schedulePlanChangeAtPeriodEnd($subscription, $plan);
                $subscription->update([
                    'pending_platform_subscription_plan_id' => $plan->id,
                    'pending_plan_effective_at' => now()->setTimestamp((int) $result['effective_at']),
                    'stripe_subscription_schedule_id' => $result['schedule_id'],
                ]);

                $message = $direction < 0
                    ? 'Downgrade scheduled. Your current plan remains active until the next renewal date.'
                    : 'Plan change scheduled for the next renewal because the billing interval is changing.';

                return back()->with('success', $message);
            });
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function cancelScheduledPlanChange(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        $company->load('subscription.pendingPlan');
        $subscription = $company->subscription;

        if (! $subscription?->pending_platform_subscription_plan_id || ! $subscription->pending_plan_effective_at) {
            return back()->with('info', 'There is no scheduled future plan change to cancel.');
        }

        try {
            Cache::lock('platform-plan-change:'.$company->id, 45)->block(5, function () use ($subscription, $stripe): void {
                $subscription->refresh();
                $stripe->cancelScheduledPlanChange($subscription);
                $subscription->update([
                    'pending_platform_subscription_plan_id' => null,
                    'pending_plan_effective_at' => null,
                    'stripe_subscription_schedule_id' => null,
                ]);
            });

            return back()->with('success', 'Scheduled plan change canceled. Your current subscription will continue unchanged.');
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
        $pendingPlanId = $record->pending_platform_subscription_plan_id;
        $hasRemotePendingUpdate = ! empty($subscription['pending_update']);

        $updates = [
            'status' => $status,
            'stripe_customer_id' => $this->stripeId($subscription['customer'] ?? null) ?: $record->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'starts_at' => $start,
            'expires_at' => $remoteEnd ?? $record->expires_at,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $remoteEnd ?? $record->current_period_ends_at,
            'auto_renew' => ! (bool) ($subscription['cancel_at_period_end'] ?? false),
            'is_enabled' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired', 'paused'], true),
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ];

        if ($resolvedPlan) {
            $updates['platform_subscription_plan_id'] = $resolvedPlan->id;
        }

        if ($pendingPlanId && $resolvedPlan && (int) $pendingPlanId === (int) $resolvedPlan->id) {
            $updates['pending_platform_subscription_plan_id'] = null;
            $updates['pending_plan_effective_at'] = null;
        } elseif ($pendingPlanId && ! $record->pending_plan_effective_at && ! $hasRemotePendingUpdate) {
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
