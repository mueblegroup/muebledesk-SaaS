<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PlatformSubscriptionPlan;
use App\Services\StripePlatformBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ClientPortalBillingController extends Controller
{
    public function index(Request $request, Company $company): View
    {
        $this->authorizeCompany($request, $company);

        return view('client-portal.billing', [
            'company' => $company->load('subscription.plan'),
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
                $subscriptionData = is_array($session['subscription'] ?? null) ? $session['subscription'] : [];
                $plan = PlatformSubscriptionPlan::find((int) ($session['metadata']['plan_id'] ?? 0));
                $startsAt = isset($subscriptionData['current_period_start']) ? now()->setTimestamp($subscriptionData['current_period_start']) : now();
                $expiresAt = isset($subscriptionData['current_period_end'])
                    ? now()->setTimestamp($subscriptionData['current_period_end'])
                    : $plan?->calculateExpiry($startsAt);

                $company->subscription()->updateOrCreate([], [
                    'platform_subscription_plan_id' => $plan?->id,
                    'status' => $subscriptionData['status'] ?? 'active',
                    'stripe_customer_id' => $session['customer'] ?? null,
                    'stripe_subscription_id' => $subscriptionData['id'] ?? ($session['subscription'] ?? null),
                    'stripe_checkout_session_id' => $session['id'] ?? $sessionId,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'current_period_starts_at' => $startsAt,
                    'current_period_ends_at' => $expiresAt,
                    'auto_renew' => (string) ($session['metadata']['auto_renew'] ?? '1') === '1',
                    'is_enabled' => true,
                    'renewal_failure_count' => 0,
                    'last_renewal_error' => null,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('client-portal.billing.index', $company)
            ->with('success', 'Subscription activated successfully.');
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

    private function authorizeCompany(Request $request, Company $company, bool $ownerOnly = false): void
    {
        abort_unless($request->user()->companies()->whereKey($company->id)->exists(), 403);
        abort_if($ownerOnly && ! $request->user()->ownsCompany($company), 403);
    }
}
