<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\SubscriptionPayment;
use App\Services\StripePlatformBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ClientPortalBillingController extends Controller
{
    public function index(Request $request, Company $company, StripePlatformBillingService $stripe): View
    {
        $this->authorizeCompany($request, $company);

        $company->load('subscription.plan');
        $subscription = $company->subscription;

        if ($subscription?->stripe_subscription_id && ! $subscription->isActive()) {
            try {
                $this->syncFromStripe($subscription, $stripe->retrieveSubscription($subscription->stripe_subscription_id));
                $company->load('subscription.plan');
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return view('client-portal.billing', [
            'company' => $company,
            'plans' => PlatformSubscriptionPlan::where('is_active', true)
                ->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function checkout(Request $request, Company $company, PlatformSubscriptionPlan $plan, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        abort_unless($plan->is_active, 404);

        $company->load('subscription.plan');
        $subscription = $company->subscription;

        try {
            if ($subscription?->isActive()) {
                if ((int) $subscription->platform_subscription_plan_id !== (int) $plan->id) {
                    return back()->with('error', 'You already have an active plan. Plan switching must be handled separately to avoid duplicate subscriptions or charges.');
                }

                $session = $stripe->createExtensionCheckoutSession(
                    $company,
                    $subscription,
                    $plan,
                    route('client-portal.billing.success', $company),
                    route('client-portal.billing.index', $company)
                );

                return redirect()->away($session['url']);
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
                    if (($session['payment_status'] ?? null) === 'paid') {
                        $this->applyExtension($company, $session, $stripe);

                        return redirect()->route('client-portal.billing.index', $company)
                            ->with('success', 'Payment received. Your current plan and next renewal date have been extended.');
                    }

                    return redirect()->route('client-portal.billing.index', $company)
                        ->with('warning', 'Your extension payment is still being processed by Stripe.');
                }

                $subscriptionPayload = $session['subscription'] ?? null;
                $subscriptionId = is_array($subscriptionPayload)
                    ? (string) ($subscriptionPayload['id'] ?? '')
                    : (string) ($subscriptionPayload ?? '');

                $subscriptionData = is_array($subscriptionPayload) ? $subscriptionPayload : [];

                if ($subscriptionId !== '') {
                    $subscriptionData = $stripe->retrieveSubscription($subscriptionId);
                }

                $plan = PlatformSubscriptionPlan::find((int) ($session['metadata']['plan_id'] ?? 0));
                $startsAt = isset($subscriptionData['current_period_start'])
                    ? now()->setTimestamp($subscriptionData['current_period_start'])
                    : now();
                $expiresAt = isset($subscriptionData['current_period_end'])
                    ? now()->setTimestamp($subscriptionData['current_period_end'])
                    : $plan?->calculateExpiry($startsAt);
                $status = (string) ($subscriptionData['status'] ?? 'incomplete');

                $record = $company->subscription()->updateOrCreate([], [
                    'platform_subscription_plan_id' => $plan?->id,
                    'status' => $status,
                    'stripe_customer_id' => $subscriptionData['customer'] ?? $session['customer'] ?? null,
                    'stripe_subscription_id' => $subscriptionId ?: null,
                    'stripe_checkout_session_id' => $session['id'] ?? $sessionId,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'current_period_starts_at' => $startsAt,
                    'current_period_ends_at' => $expiresAt,
                    'auto_renew' => ! (bool) ($subscriptionData['cancel_at_period_end'] ?? false),
                    'is_enabled' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true),
                    'renewal_failure_count' => 0,
                    'last_renewal_error' => null,
                ]);

                if (($session['payment_status'] ?? null) === 'paid' && $record->status === 'incomplete') {
                    $latest = $stripe->retrieveSubscription((string) $record->stripe_subscription_id);
                    $this->syncFromStripe($record, $latest);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('client-portal.billing.index', $company)
            ->with('success', 'Payment received. Subscription status has been refreshed from Stripe.');
    }

    public function toggleAutoRenew(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        $subscription = $company->subscription;
        abort_unless($subscription, 404);

        try {
            $stripe->setAutoRenew($subscription, $request->boolean('auto_renew'));
            return back()->with('success', 'Auto-renew setting updated.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    public function portal(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);
        try {
            return redirect()->away($stripe->createBillingPortalSession($company, route('client-portal.billing.index', $company))['url']);
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', $exception->getMessage());
        }
    }

    private function applyExtension(Company $company, array $session, StripePlatformBillingService $stripe): void
    {
        $subscription = $company->subscription()->with('plan')->first();
        $plan = PlatformSubscriptionPlan::find((int) ($session['metadata']['plan_id'] ?? 0));
        $sessionId = (string) ($session['id'] ?? '');

        if (! $subscription || ! $plan || ! $subscription->isActive() || (int) $subscription->platform_subscription_plan_id !== (int) $plan->id) {
            return;
        }

        if ($sessionId !== '' && $subscription->stripe_checkout_session_id === $sessionId) {
            return;
        }

        $base = $subscription->expires_at && $subscription->expires_at->isFuture()
            ? $subscription->expires_at->copy()
            : now();
        $newExpiry = $plan->calculateExpiry($base);

        if ($subscription->auto_renew && $subscription->stripe_subscription_id && $newExpiry) {
            try {
                $stripe->postponeRenewalTo($subscription, $newExpiry);
            } catch (Throwable $exception) {
                report($exception);
                try {
                    $stripe->setAutoRenew($subscription, false);
                } catch (Throwable $fallbackException) {
                    report($fallbackException);
                }
                $subscription->auto_renew = false;
            }
        }

        $subscription->update([
            'status' => 'active',
            'is_enabled' => true,
            'expires_at' => $newExpiry,
            'stripe_checkout_session_id' => $sessionId ?: $subscription->stripe_checkout_session_id,
            'auto_renew' => (bool) $subscription->auto_renew,
            'renewal_failure_count' => 0,
            'last_renewal_error' => null,
        ]);

        SubscriptionPayment::updateOrCreate(
            ['provider' => 'stripe', 'provider_invoice_id' => $sessionId],
            [
                'company_id' => $company->id,
                'company_subscription_id' => $subscription->id,
                'platform_subscription_plan_id' => $plan->id,
                'provider_payment_id' => $session['payment_intent'] ?? null,
                'provider_customer_id' => $session['customer'] ?? $subscription->stripe_customer_id,
                'status' => 'paid',
                'amount' => ((int) ($session['amount_total'] ?? 0)) / 100,
                'currency' => strtoupper($session['currency'] ?? $plan->currency),
                'description' => $plan->name.' extension',
                'paid_at' => now(),
                'failed_at' => null,
                'failure_message' => null,
                'metadata' => [
                    'checkout_session_id' => $sessionId,
                    'purchase_type' => 'extension',
                    'extended_from' => $base->toIso8601String(),
                    'extended_until' => $newExpiry?->toIso8601String(),
                ],
            ]
        );
    }

    private function syncFromStripe(CompanySubscription $record, array $subscription): void
    {
        $status = (string) ($subscription['status'] ?? $record->status);
        $start = isset($subscription['current_period_start'])
            ? now()->setTimestamp($subscription['current_period_start'])
            : $record->starts_at;
        $remoteEnd = isset($subscription['current_period_end'])
            ? now()->setTimestamp($subscription['current_period_end'])
            : null;
        $end = $record->expires_at && $remoteEnd && $record->expires_at->greaterThan($remoteEnd)
            ? $record->expires_at
            : ($remoteEnd ?? $record->expires_at);

        $record->update([
            'status' => $status,
            'stripe_customer_id' => $subscription['customer'] ?? $record->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'starts_at' => $start,
            'expires_at' => $end,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $remoteEnd ?? $record->current_period_ends_at,
            'auto_renew' => ! (bool) ($subscription['cancel_at_period_end'] ?? false),
            'is_enabled' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true),
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ]);
    }

    private function authorizeCompany(Request $request, Company $company, bool $ownerOnly = false): void
    {
        abort_unless($request->user()->companies()->whereKey($company->id)->exists(), 403);
        abort_if($ownerOnly && ! $request->user()->ownsCompany($company), 403);
    }
}
