<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Services\StripePlatformBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class StripePlatformWebhookController extends Controller
{
    public function handle(Request $request, StripePlatformBillingService $stripe): Response
    {
        try {
            $event = $stripe->verifyWebhook($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (Throwable $exception) {
            report($exception);
            return response('Invalid webhook', 400);
        }

        $object = $event['data']['object'] ?? [];
        match ($event['type'] ?? '') {
            'checkout.session.completed' => $this->checkoutCompleted($object),
            'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted' => $this->syncSubscription($object),
            'invoice.payment_succeeded' => $this->paymentSucceeded($object),
            'invoice.payment_failed' => $this->paymentFailed($object),
            default => null,
        };

        return response('ok');
    }

    private function checkoutCompleted(array $session): void
    {
        $company = Company::find((int) ($session['metadata']['company_id'] ?? $session['client_reference_id'] ?? 0));
        $plan = PlatformSubscriptionPlan::find((int) ($session['metadata']['plan_id'] ?? 0));
        if (! $company || ! $plan) return;

        $company->subscription()->updateOrCreate([], [
            'platform_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'stripe_customer_id' => $session['customer'] ?? null,
            'stripe_subscription_id' => $session['subscription'] ?? null,
            'stripe_checkout_session_id' => $session['id'] ?? null,
            'starts_at' => now(),
            'expires_at' => $plan->calculateExpiry(),
            'auto_renew' => (string) ($session['metadata']['auto_renew'] ?? '1') === '1',
            'is_enabled' => true,
            'renewal_failure_count' => 0,
            'last_renewal_error' => null,
        ]);
    }

    private function syncSubscription(array $subscription): void
    {
        $record = $this->findSubscription($subscription);
        if (! $record) return;

        $status = $subscription['status'] ?? $record->status;
        $periodStart = isset($subscription['current_period_start']) ? now()->setTimestamp($subscription['current_period_start']) : $record->starts_at;
        $periodEnd = isset($subscription['current_period_end']) ? now()->setTimestamp($subscription['current_period_end']) : $record->expires_at;

        $record->update([
            'platform_subscription_plan_id' => (int) ($subscription['metadata']['plan_id'] ?? $record->platform_subscription_plan_id) ?: null,
            'status' => $status,
            'stripe_customer_id' => $subscription['customer'] ?? $record->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'starts_at' => $periodStart,
            'expires_at' => $periodEnd,
            'current_period_starts_at' => $periodStart,
            'current_period_ends_at' => $periodEnd,
            'auto_renew' => ! (bool) ($subscription['cancel_at_period_end'] ?? false),
            'is_enabled' => ! in_array($status, ['canceled', 'unpaid'], true),
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ]);
    }

    private function paymentSucceeded(array $invoice): void
    {
        $record = CompanySubscription::where('stripe_subscription_id', $invoice['subscription'] ?? '')->first();
        if (! $record) return;

        $period = $invoice['lines']['data'][0]['period'] ?? [];
        $record->update([
            'status' => 'active',
            'is_enabled' => true,
            'starts_at' => isset($period['start']) ? now()->setTimestamp($period['start']) : ($record->starts_at ?? now()),
            'expires_at' => isset($period['end']) ? now()->setTimestamp($period['end']) : $record->plan?->calculateExpiry($record->expires_at && $record->expires_at->isFuture() ? $record->expires_at : now()),
            'renewal_failure_count' => 0,
            'last_renewal_attempt_at' => now(),
            'last_renewal_error' => null,
        ]);
    }

    private function paymentFailed(array $invoice): void
    {
        $record = CompanySubscription::where('stripe_subscription_id', $invoice['subscription'] ?? '')->first();
        if (! $record) return;

        $record->increment('renewal_failure_count');
        $record->update([
            'status' => 'past_due',
            'last_renewal_attempt_at' => now(),
            'last_renewal_error' => $invoice['last_finalization_error']['message'] ?? 'Automatic renewal payment failed.',
        ]);
    }

    private function findSubscription(array $subscription): ?CompanySubscription
    {
        $companyId = (int) ($subscription['metadata']['company_id'] ?? 0);
        return CompanySubscription::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when(! $companyId, fn ($query) => $query->where('stripe_subscription_id', $subscription['id'] ?? ''))
            ->first();
    }
}
