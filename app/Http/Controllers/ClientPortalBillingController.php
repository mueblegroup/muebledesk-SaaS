<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
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
        $autoRenew = $request->boolean('auto_renew', $plan->auto_renew_default);

        try {
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

    private function syncFromStripe(CompanySubscription $record, array $subscription): void
    {
        $status = (string) ($subscription['status'] ?? $record->status);
        $start = isset($subscription['current_period_start'])
            ? now()->setTimestamp($subscription['current_period_start'])
            : $record->starts_at;
        $end = isset($subscription['current_period_end'])
            ? now()->setTimestamp($subscription['current_period_end'])
            : $record->expires_at;

        $record->update([
            'status' => $status,
            'stripe_customer_id' => $subscription['customer'] ?? $record->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'starts_at' => $start,
            'expires_at' => $end,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $end,
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
