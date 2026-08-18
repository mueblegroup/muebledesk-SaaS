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

            if ($existing->status === 'processing') {
                // A concurrent delivery may still be working. Return a non-2xx
                // response so Stripe retries instead of considering it complete.
                return response('Webhook already processing', 409);
            }

            // Failed events are deliberately retriable. Stripe will deliver the same
            // event id again after a 5xx response, so reset the ledger and process it.
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
        $company = Company::find((int) ($session['metadata']['company_id'] ?? $session['client_reference_id'] ?? 0));
        $plan = PlatformSubscriptionPlan::find((int) ($session['metadata']['plan_id'] ?? 0));

        if (! $company || ! $plan) {
            return;
        }

        if (($session['metadata']['purchase_type'] ?? null) === 'extension') {
            if (($session['payment_status'] ?? null) === 'paid') {
                $this->applyExtensionCheckout($company, $plan, $session, $stripe);
            }
            return;
        }

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

    private function applyExtensionCheckout(Company $company, PlatformSubscriptionPlan $plan, array $session, StripePlatformBillingService $stripe): void
    {
        $record = $company->subscription()->with('plan')->first();
        $sessionId = (string) ($session['id'] ?? '');

        if (! $record || ! $record->isActive() || (int) $record->platform_subscription_plan_id !== (int) $plan->id) {
            return;
        }

        if ($sessionId !== '' && $record->stripe_checkout_session_id === $sessionId) {
            return;
        }

        $base = $record->expires_at && $record->expires_at->isFuture()
            ? $record->expires_at->copy()
            : now();
        $newExpiry = $plan->calculateExpiry($base);

        if ($record->auto_renew && $record->stripe_subscription_id && $newExpiry) {
            try {
                $stripe->postponeRenewalTo($record, $newExpiry);
            } catch (Throwable $exception) {
                report($exception);
                try {
                    $stripe->setAutoRenew($record, false);
                } catch (Throwable $fallbackException) {
                    report($fallbackException);
                }
                $record->auto_renew = false;
            }
        }

        $record->update([
            'status' => 'active',
            'is_enabled' => true,
            'expires_at' => $newExpiry,
            'stripe_checkout_session_id' => $sessionId ?: $record->stripe_checkout_session_id,
            'auto_renew' => (bool) $record->auto_renew,
            'renewal_failure_count' => 0,
            'last_renewal_error' => null,
        ]);

        SubscriptionPayment::updateOrCreate(
            ['provider' => 'stripe', 'provider_invoice_id' => $sessionId],
            [
                'company_id' => $company->id,
                'company_subscription_id' => $record->id,
                'platform_subscription_plan_id' => $plan->id,
                'provider_payment_id' => $session['payment_intent'] ?? null,
                'provider_customer_id' => $session['customer'] ?? $record->stripe_customer_id,
                'status' => 'paid',
                'amount' => ((int) ($session['amount_total'] ?? 0)) / 100,
                'currency' => strtoupper($session['currency'] ?? $plan->currency),
                'description' => $plan->name.' extension',
                'failure_message' => null,
                'paid_at' => now(),
                'failed_at' => null,
                'metadata' => [
                    'checkout_session_id' => $sessionId,
                    'purchase_type' => 'extension',
                    'extended_from' => $base->toIso8601String(),
                    'extended_until' => $newExpiry?->toIso8601String(),
                ],
            ]
        );
    }

    private function syncSubscription(array $subscription): void
    {
        $record = $this->findSubscription($subscription);
        if (! $record) {
            return;
        }

        $status = $subscription['status'] ?? $record->status;
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
            'platform_subscription_plan_id' => (int) ($subscription['metadata']['plan_id'] ?? $record->platform_subscription_plan_id) ?: null,
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

    private function paymentSucceeded(array $invoice): void
    {
        $record = CompanySubscription::where('stripe_subscription_id', $invoice['subscription'] ?? '')->first();
        if (! $record) {
            return;
        }

        $period = $invoice['lines']['data'][0]['period'] ?? [];
        $periodEnd = isset($period['end']) ? now()->setTimestamp($period['end']) : null;
        $expiresAt = $record->expires_at && $periodEnd && $record->expires_at->greaterThan($periodEnd)
            ? $record->expires_at
            : ($periodEnd ?? $record->plan?->calculateExpiry($record->expires_at && $record->expires_at->isFuture() ? $record->expires_at : now()));

        $record->update([
            'status' => 'active',
            'is_enabled' => true,
            'starts_at' => isset($period['start']) ? now()->setTimestamp($period['start']) : ($record->starts_at ?? now()),
            'expires_at' => $expiresAt,
            'renewal_failure_count' => 0,
            'last_renewal_attempt_at' => now(),
            'last_renewal_error' => null,
        ]);

        $this->recordPayment($record, $invoice, 'paid');
    }

    private function paymentFailed(array $invoice): void
    {
        $record = CompanySubscription::where('stripe_subscription_id', $invoice['subscription'] ?? '')->first();
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
                'provider_payment_id' => $invoice['payment_intent'] ?? $invoice['charge'] ?? null,
                'provider_customer_id' => $invoice['customer'] ?? $record->stripe_customer_id,
                'status' => $status,
                'amount' => ((int) ($invoice['amount_paid'] ?? $invoice['amount_due'] ?? 0)) / 100,
                'currency' => strtoupper($invoice['currency'] ?? 'MYR'),
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
