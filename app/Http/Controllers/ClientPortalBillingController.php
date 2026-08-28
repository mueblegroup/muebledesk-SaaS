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
use RuntimeException;
use Throwable;

class ClientPortalBillingController extends Controller
{
    private const TERMINAL_SUBSCRIPTION_STATUSES = ['canceled', 'incomplete_expired'];

    public function index(Request $request, Company $company, StripePlatformBillingService $stripe): View
    {
        $this->authorizeCompany($request, $company);

        $company->load('subscription.plan');
        $subscription = $company->subscription;

        // Refresh Stripe state whenever the billing page is opened. Webhooks are
        // still the primary synchronization mechanism, but this protects the UI
        // from stale renewal/cancellation state if a webhook was delayed.
        if ($subscription?->stripe_subscription_id) {
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
                ->orderBy('sort_order')
                ->orderBy('price')
                ->get(),
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
                return back()->with(
                    'info',
                    $subscription->auto_renew
                        ? 'Your current subscription is already active and will renew automatically. No additional payment is required.'
                        : 'Your current subscription remains active until the end of its paid period. You can manage it from Payment settings.'
                );
            }

            // past_due, unpaid, paused, and other non-terminal Stripe states are
            // billing-recovery states, not permission to create a second
            // subscription. The customer must resolve the existing Stripe
            // subscription first.
            if ($this->hasRecoverableStripeSubscription($subscription)) {
                return back()->with(
                    'error',
                    'An existing Stripe subscription still requires billing attention. Please use Payment settings to resolve it before purchasing another plan.'
                );
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

                // Manual prepaid extensions are intentionally no longer part of
                // the billing model. Never mutate local entitlement from an old
                // extension Checkout Session.
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

        $startsAt = isset($subscriptionData['current_period_start'])
            ? now()->setTimestamp($subscriptionData['current_period_start'])
            : now();
        $remoteEnd = isset($subscriptionData['current_period_end'])
            ? now()->setTimestamp($subscriptionData['current_period_end'])
            : null;
        $expiresAt = $remoteEnd ?? $plan->calculateExpiry($startsAt);

        $subscription = CompanySubscription::updateOrCreate(
            ['company_id' => $company->id],
            [
                'platform_subscription_plan_id' => $plan->id,
                'status' => (string) ($subscriptionData['status'] ?? 'active'),
                'seats' => max(1, (int) ($session['metadata']['seats'] ?? 1)),
                'is_enabled' => true,
                'auto_renew' => ! (bool) ($subscriptionData['cancel_at_period_end'] ?? false),
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'current_period_starts_at' => $startsAt,
                'current_period_ends_at' => $remoteEnd,
                'stripe_customer_id' => is_array($session['customer'] ?? null)
                    ? ($session['customer']['id'] ?? null)
                    : ($session['customer'] ?? null),
                'stripe_subscription_id' => $subscriptionId,
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
                'provider_payment_id' => is_array($session['payment_intent'] ?? null)
                    ? ($session['payment_intent']['id'] ?? null)
                    : ($session['payment_intent'] ?? null),
                'provider_customer_id' => is_array($session['customer'] ?? null)
                    ? ($session['customer']['id'] ?? null)
                    : ($session['customer'] ?? null),
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

        $metadataCompanyId = (int) ($session['metadata']['company_id'] ?? 0);
        $referenceCompanyId = (int) ($session['client_reference_id'] ?? 0);
        if ($metadataCompanyId !== (int) $company->id || $referenceCompanyId !== (int) $company->id) {
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

    private function syncFromStripe(CompanySubscription $record, array $subscription): void
    {
        $status = (string) ($subscription['status'] ?? $record->status);
        $start = isset($subscription['current_period_start'])
            ? now()->setTimestamp($subscription['current_period_start'])
            : $record->starts_at;
        $remoteEnd = isset($subscription['current_period_end'])
            ? now()->setTimestamp($subscription['current_period_end'])
            : null;

        $record->update([
            'status' => $status,
            'stripe_customer_id' => is_array($subscription['customer'] ?? null)
                ? ($subscription['customer']['id'] ?? $record->stripe_customer_id)
                : ($subscription['customer'] ?? $record->stripe_customer_id),
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'starts_at' => $start,
            'expires_at' => $remoteEnd ?? $record->expires_at,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $remoteEnd ?? $record->current_period_ends_at,
            'auto_renew' => ! (bool) ($subscription['cancel_at_period_end'] ?? false),
            'is_enabled' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired', 'paused'], true),
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ]);
    }

    private function authorizeCompany(Request $request, Company $company, bool $ownerOnly = false): void
    {
        $membership = $request->user()->companies()
            ->whereKey($company->id)
            ->first();

        abort_unless($membership, 403);

        if ($ownerOnly) {
            abort_unless($membership->pivot->role === 'owner', 403);

            return;
        }

        abort_unless(in_array($membership->pivot->role, ['owner', 'admin'], true), 403);
    }
}
