<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\PlatformWebhookEvent;
use App\Models\SubscriptionPayment;
use App\Services\StripePlatformBillingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class StripePlatformWebhookController extends Controller
{
    public function handle(Request $request, StripePlatformBillingService $stripe): Response
    {
        $payload = $request->getContent();

        try {
            $event = $stripe->verifyWebhook($payload, (string) $request->header('Stripe-Signature'));
        } catch (Throwable $exception) {
            report($exception);

            return response('Invalid webhook', 400);
        }

        $eventId = (string) ($event['id'] ?? '');
        if ($eventId === '') {
            return response('Missing event id', 400);
        }

        try {
            $ledger = PlatformWebhookEvent::create([
                'provider' => 'stripe',
                'event_id' => $eventId,
                'event_type' => $event['type'] ?? null,
                'status' => 'processing',
                'payload_hash' => hash('sha256', $payload),
            ]);
        } catch (QueryException $exception) {
            $existing = PlatformWebhookEvent::query()
                ->where('provider', 'stripe')
                ->where('event_id', $eventId)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            if ($existing->status === 'processed') {
                return response('ok');
            }

            $processingIsFresh = $existing->status === 'processing'
                && $existing->updated_at
                && $existing->updated_at->greaterThan(now()->subMinutes(5));

            if ($processingIsFresh) {
                return response('Webhook already processing', 409);
            }

            $existing->update([
                'status' => 'processing',
                'processed_at' => null,
                'error_message' => null,
                'payload_hash' => hash('sha256', $payload),
                'event_type' => $event['type'] ?? $existing->event_type,
            ]);
            $ledger = $existing;
        }

        try {
            $object = $event['data']['object'] ?? [];

            match ($event['type'] ?? '') {
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded' => $this->checkoutCompleted($object, $stripe),
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted' => $this->syncSubscription($object),
                'invoice.payment_succeeded' => $this->paymentSucceeded($object),
                'invoice.payment_failed' => $this->paymentFailed($object),
                default => null,
            };

            $ledger->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $ledger->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 65535),
            ]);

            report($exception);

            return response('Webhook processing failed', 500);
        }

        return response('ok');
    }

    private function checkoutCompleted(array $session, StripePlatformBillingService $stripe): void
    {
        // Legacy prepaid extension sessions must never mutate entitlement now
        // that the product uses a single recurring-subscription lifecycle.
        if (($session['metadata']['purchase_type'] ?? null) === 'extension') {
            return;
        }

        $company = Company::find((int) ($session['metadata']['company_id'] ?? $session['client_reference_id'] ?? 0));
        $plan = PlatformSubscriptionPlan::find((int) ($session['metadata']['plan_id'] ?? 0));
        $subscriptionValue = $session['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionValue)
            ? (string) ($subscriptionValue['id'] ?? '')
            : (string) ($subscriptionValue ?? '');

        if (! $company || ! $plan || $subscriptionId === '') {
            return;
        }

        $stripeSubscription = $stripe->retrieveSubscription($subscriptionId);
        $status = (string) ($stripeSubscription['status'] ?? 'incomplete');
        $startsAt = isset($stripeSubscription['current_period_start'])
            ? now()->setTimestamp($stripeSubscription['current_period_start'])
            : now();
        $periodEnd = isset($stripeSubscription['current_period_end'])
            ? now()->setTimestamp($stripeSubscription['current_period_end'])
            : $plan->calculateExpiry($startsAt);
        $customerValue = $stripeSubscription['customer'] ?? $session['customer'] ?? null;
        $customerId = is_array($customerValue)
            ? ($customerValue['id'] ?? null)
            : $customerValue;

        $company->subscription()->updateOrCreate([], [
            'platform_subscription_plan_id' => $plan->id,
            'status' => $status,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscriptionId,
            'stripe_checkout_session_id' => $session['id'] ?? null,
            'starts_at' => $startsAt,
            'expires_at' => $periodEnd,
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $periodEnd,
            'auto_renew' => ! (bool) ($stripeSubscription['cancel_at_period_end'] ?? false),
            'is_enabled' => in_array($status, ['active', 'trialing'], true),
            'renewal_failure_count' => 0,
            'last_renewal_error' => null,
        ]);
    }

    private function syncSubscription(array $subscription): void
    {
        $record = $this->findSubscription($subscription);
        if (! $record) {
            return;
        }

        $status = (string) ($subscription['status'] ?? $record->status);
        $start = isset($subscription['current_period_start'])
            ? now()->setTimestamp($subscription['current_period_start'])
            : $record->starts_at;
        $periodEnd = isset($subscription['current_period_end'])
            ? now()->setTimestamp($subscription['current_period_end'])
            : $record->current_period_ends_at;
        $customerValue = $subscription['customer'] ?? null;
        $customerId = is_array($customerValue)
            ? ($customerValue['id'] ?? $record->stripe_customer_id)
            : ($customerValue ?? $record->stripe_customer_id);

        $record->update([
            'platform_subscription_plan_id' => (int) ($subscription['metadata']['plan_id'] ?? $record->platform_subscription_plan_id) ?: null,
            'status' => $status,
            'stripe_customer_id' => $customerId,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'starts_at' => $start,
            'expires_at' => $periodEnd ?? $record->expires_at,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $periodEnd,
            'auto_renew' => ! (bool) ($subscription['cancel_at_period_end'] ?? false),
            'is_enabled' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired', 'paused'], true),
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ]);
    }

    private function paymentSucceeded(array $invoice): void
    {
        $subscriptionValue = $invoice['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionValue)
            ? (string) ($subscriptionValue['id'] ?? '')
            : (string) ($subscriptionValue ?? '');
        $record = CompanySubscription::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $record) {
            return;
        }

        $period = $invoice['lines']['data'][0]['period'] ?? [];
        $periodStart = isset($period['start']) ? now()->setTimestamp($period['start']) : ($record->starts_at ?? now());
        $periodEnd = isset($period['end'])
            ? now()->setTimestamp($period['end'])
            : $record->plan?->calculateExpiry($periodStart);

        $record->update([
            'status' => 'active',
            'is_enabled' => true,
            'starts_at' => $periodStart,
            'expires_at' => $periodEnd ?? $record->expires_at,
            'current_period_starts_at' => $periodStart,
            'current_period_ends_at' => $periodEnd ?? $record->current_period_ends_at,
            'renewal_failure_count' => 0,
            'last_renewal_attempt_at' => now(),
            'last_renewal_error' => null,
        ]);

        $this->recordPayment($record, $invoice, 'paid');
    }

    private function paymentFailed(array $invoice): void
    {
        $subscriptionValue = $invoice['subscription'] ?? null;
        $subscriptionId = is_array($subscriptionValue)
            ? (string) ($subscriptionValue['id'] ?? '')
            : (string) ($subscriptionValue ?? '');
        $record = CompanySubscription::where('stripe_subscription_id', $subscriptionId)->first();

        if (! $record) {
            return;
        }

        $record->increment('renewal_failure_count');
        $message = $invoice['last_finalization_error']['message'] ?? 'Automatic renewal payment failed.';
        $record->update([
            'status' => 'past_due',
            'last_renewal_attempt_at' => now(),
            'last_renewal_error' => $message,
        ]);

        $this->recordPayment($record, $invoice, 'failed', $message);
    }

    private function recordPayment(CompanySubscription $record, array $invoice, string $status, ?string $failure = null): void
    {
        SubscriptionPayment::updateOrCreate(
            ['provider' => 'stripe', 'provider_invoice_id' => $invoice['id'] ?? null],
            [
                'company_id' => $record->company_id,
                'company_subscription_id' => $record->id,
                'platform_subscription_plan_id' => $record->platform_subscription_plan_id,
                'provider_payment_id' => is_array($invoice['payment_intent'] ?? null)
                    ? ($invoice['payment_intent']['id'] ?? null)
                    : ($invoice['payment_intent'] ?? $invoice['charge'] ?? null),
                'provider_customer_id' => is_array($invoice['customer'] ?? null)
                    ? ($invoice['customer']['id'] ?? $record->stripe_customer_id)
                    : ($invoice['customer'] ?? $record->stripe_customer_id),
                'status' => $status,
                'amount' => ((int) ($invoice['amount_paid'] ?? $invoice['amount_due'] ?? 0)) / 100,
                'currency' => strtoupper((string) ($invoice['currency'] ?? 'MYR')),
                'description' => $invoice['description'] ?? 'Subscription payment',
                'failure_message' => $failure,
                'paid_at' => $status === 'paid' ? now() : null,
                'failed_at' => $status === 'failed' ? now() : null,
                'metadata' => [
                    'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
                    'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
                ],
            ]
        );
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
