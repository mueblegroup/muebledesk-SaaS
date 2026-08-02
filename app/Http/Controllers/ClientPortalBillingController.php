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
                ->orderBy('sort_order')
                ->orderBy('price_per_seat')
                ->get(),
            'seatsUsed' => $company->seatsUsed(),
        ]);
    }

    public function checkout(Request $request, Company $company, PlatformSubscriptionPlan $plan, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);

        $validated = $request->validate([
            'seats' => ['required', 'integer', 'min:'.$plan->minimum_seats],
        ]);

        $seats = (int) $validated['seats'];
        abort_if($plan->maximum_seats && $seats > $plan->maximum_seats, 422, 'Seat quantity exceeds this plan limit.');
        abort_if($seats < $company->seatsUsed(), 422, 'Seat quantity cannot be lower than current team usage.');

        try {
            $session = $stripe->createCheckoutSession(
                $company,
                $plan,
                $seats,
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

                $company->subscription()->updateOrCreate([], [
                    'platform_subscription_plan_id' => (int) ($session['metadata']['plan_id'] ?? 0) ?: null,
                    'seats' => (int) ($session['metadata']['seats'] ?? 1),
                    'status' => $subscriptionData['status'] ?? 'active',
                    'stripe_customer_id' => $session['customer'] ?? null,
                    'stripe_subscription_id' => $subscriptionData['id'] ?? ($session['subscription'] ?? null),
                    'stripe_checkout_session_id' => $session['id'] ?? $sessionId,
                    'trial_ends_at' => isset($subscriptionData['trial_end']) ? now()->setTimestamp($subscriptionData['trial_end']) : null,
                    'current_period_starts_at' => isset($subscriptionData['current_period_start']) ? now()->setTimestamp($subscriptionData['current_period_start']) : null,
                    'current_period_ends_at' => isset($subscriptionData['current_period_end']) ? now()->setTimestamp($subscriptionData['current_period_end']) : null,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('client-portal.billing.index', $company)
            ->with('success', 'Stripe subscription setup completed.');
    }

    public function portal(Request $request, Company $company, StripePlatformBillingService $stripe): RedirectResponse
    {
        $this->authorizeCompany($request, $company, ownerOnly: true);

        try {
            $session = $stripe->createBillingPortalSession($company, route('client-portal.billing.index', $company));
            return redirect()->away($session['url']);
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
