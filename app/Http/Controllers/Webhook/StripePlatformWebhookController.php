<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Services\StripePlatformBillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class StripePlatformWebhookController extends Controller
{
    public function handle(Request $request, StripePlatformBillingService $stripe): Response
    {
        try {
            $event = $stripe->verifyWebhook(
                $request->getContent(),
                (string) $request->header('Stripe-Signature')
            );
        } catch (Throwable $exception) {
            report($exception);
            return response('Invalid webhook', 400);
        }

        $object = $event['data']['object'] ?? [];

        match ($event['type'] ?? '') {
            'checkout.session.completed' => $this->checkoutCompleted($object),
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->syncSubscription($object),
            default => null,
        };

        return response('ok');
    }

    private function checkoutCompleted(array $session): void
    {
        $companyId = (int) ($session['metadata']['company_id'] ?? $session['client_reference_id'] ?? 0);
        $company = Company::find($companyId);

        if (! $company) {
            return;
        }

        $company->subscription()->updateOrCreate([], [
            'platform_subscription_plan_id' => (int) ($session['metadata']['plan_id'] ?? 0) ?: null,
            'seats' => (int) ($session['metadata']['seats'] ?? 1),
            'status' => 'active',
            'stripe_customer_id' => $session['customer'] ?? null,
            'stripe_subscription_id' => $session['subscription'] ?? null,
            'stripe_checkout_session_id' => $session['id'] ?? null,
        ]);
    }

    private function syncSubscription(array $subscription): void
    {
        $companyId = (int) ($subscription['metadata']['company_id'] ?? 0);
        $record = CompanySubscription::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when(! $companyId, fn ($query) => $query->where('stripe_subscription_id', $subscription['id'] ?? ''))
            ->first();

        if (! $record) {
            return;
        }

        $record->update([
            'platform_subscription_plan_id' => (int) ($subscription['metadata']['plan_id'] ?? $record->platform_subscription_plan_id) ?: null,
            'seats' => (int) ($subscription['metadata']['seats'] ?? $subscription['items']['data'][0]['quantity'] ?? $record->seats),
            'status' => $subscription['status'] ?? $record->status,
            'stripe_customer_id' => $subscription['customer'] ?? $record->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'trial_ends_at' => isset($subscription['trial_end']) ? now()->setTimestamp($subscription['trial_end']) : null,
            'current_period_starts_at' => isset($subscription['current_period_start']) ? now()->setTimestamp($subscription['current_period_start']) : null,
            'current_period_ends_at' => isset($subscription['current_period_end']) ? now()->setTimestamp($subscription['current_period_end']) : null,
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ]);
    }
}
