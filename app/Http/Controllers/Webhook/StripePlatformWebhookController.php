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
                'subscription_schedule.completed',
                'subscription_schedule.released',
                'subscription_schedule.canceled' => $this->syncScheduleLifecycle($object),
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
        if (($session['metadata']['purchase_type'] ?? null) === 'extension') {
            return;
        }

        $company = Company::find((int) ($session['metadata']['company_id'] ?? $session['client_reference_id'] ?? 0));
        $subscriptionId = $this->stripeId($session['subscription'] ?? null);

        if (! $company || ! $subscriptionId) {
            return;
        }

        $stripeSubscription = $stripe->retrieveSubscription($subscriptionId);
        $plan = $this->planFromSubscription($stripeSubscription)
            ?? PlatformSubscriptionPlan::find((int) ($session['metadata']['plan_id'] ?? 0));

        if (! $plan) {
            return;
        }

        $status = (string) ($stripeSubscription['status'] ?? 'incomplete');
        $startsAt = isset($stripeSubscription['current_period_start'])
            ? now()->setTimestamp($stripeSubscription['current_period_start'])
            : now();
        $periodEnd = isset($stripeSubscription['current_period_end'])
            ? now()->setTimestamp($stripeSubscription['current_period_end'])
            : $plan->calculateExpiry($startsAt);

        $company->subscription()->updateOrCreate([], [
            'platform_subscription_plan_id' => $plan->id,
            'pending_platform_subscription_plan_id' => null,
            'pending_plan_effective_at' => null,
            'status' => $status,
            'stripe_customer_id' => $this->stripeId($stripeSubscription['customer'] ?? $session['customer'] ?? null),
            'stripe_subscription_id' => $subscriptionId,
            'stripe_subscription_schedule_id' => $this->stripeId($stripeSubscription['schedule'] ?? null),
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
        $resolvedPlan = $this->planFromSubscription($subscription)
            ?? PlatformSubscriptionPlan::find((int) ($subscription['metadata']['plan_id'] ?? 0));
        $scheduleId = $this->stripeId($subscription['schedule'] ?? null);
        $hasPendingUpdate = ! empty($subscription['pending_update']);
        $pendingPlanId = (int) ($record->pending_platform_subscription_plan_id ?? 0);
        $pendingEffectiveAt = $record->pending_plan_effective_at;
        $deferredPlanStillPending = $pendingPlanId > 0
            && $pendingEffectiveAt
            && $pendingEffectiveAt->isFuture()
            && $resolvedPlan
            && (int) $resolvedPlan->id === $pendingPlanId;

        $updates = [
            'status' => $status,
            'stripe_customer_id' => $this->stripeId($subscription['customer'] ?? null) ?: $record->stripe_customer_id,
            'stripe_subscription_id' => $subscription['id'] ?? $record->stripe_subscription_id,
            'stripe_subscription_schedule_id' => $scheduleId,
            'starts_at' => $start,
            'expires_at' => $periodEnd ?? $record->expires_at,
            'current_period_starts_at' => $start,
            'current_period_ends_at' => $periodEnd,
            'auto_renew' => ! (bool) ($subscription['cancel_at_period_end'] ?? false),
            'is_enabled' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired', 'paused'], true),
            'cancel_at' => isset($subscription['cancel_at']) ? now()->setTimestamp($subscription['cancel_at']) : null,
            'canceled_at' => isset($subscription['canceled_at']) ? now()->setTimestamp($subscription['canceled_at']) : null,
        ];

        if ($resolvedPlan && ! $deferredPlanStillPending) {
            $updates['platform_subscription_plan_id'] = $resolvedPlan->id;

            if ($pendingPlanId === (int) $resolvedPlan->id) {
                $updates['pending_platform_subscription_plan_id'] = null;
                $updates['pending_plan_effective_at'] = null;
            }
        }

        if ($pendingPlanId > 0
            && ! $pendingEffectiveAt
            && ! $hasPendingUpdate
            && (! $resolvedPlan || (int) $resolvedPlan->id !== $pendingPlanId)) {
            $updates['pending_platform_subscription_plan_id'] = null;
        }

        $record->update($updates);
    }

    private function paymentSucceeded(array $invoice): void
    {
        $record = $this->subscriptionFromInvoice($invoice);
        if (! $record) {
            return;
        }

        $period = $invoice['lines']['data'][0]['period'] ?? [];
        $periodStart = isset($period['start']) ? now()->setTimestamp($period['start']) : ($record->starts_at ?? now());
        $periodEnd = isset($period['end']) ? now()->setTimestamp($period['end']) : $record->plan?->calculateExpiry($periodStart);
        $resolvedPlan = $this->planFromInvoice($invoice, $record);
        $updates = [
            'status' => 'active',
            'is_enabled' => true,
            'starts_at' => $periodStart,
            'expires_at' => $periodEnd ?? $record->expires_at,
            'current_period_starts_at' => $periodStart,
            'current_period_ends_at' => $periodEnd ?? $record->current_period_ends_at,
            'renewal_failure_count' => 0,
            'last_renewal_attempt_at' => now(),
            'last_renewal_error' => null,
        ];

        if ($resolvedPlan) {
            $pendingMatches = (int) $record->pending_platform_subscription_plan_id === (int) $resolvedPlan->id;
            $effectiveReached = ! $record->pending_plan_effective_at
                || $periodStart->greaterThanOrEqualTo($record->pending_plan_effective_at);

            if (! $pendingMatches || $effectiveReached) {
                $updates['platform_subscription_plan_id'] = $resolvedPlan->id;
            }

            if ($pendingMatches && $effectiveReached) {
                $updates['pending_platform_subscription_plan_id'] = null;
                $updates['pending_plan_effective_at'] = null;
            }
        }

        $record->update($updates);
        $this->recordPayment($record->fresh(), $invoice, 'paid');
    }

    private function paymentFailed(array $invoice): void
    {
        $record = $this->subscriptionFromInvoice($invoice);
        if (! $record) {
            return;
        }

        $record->increment('renewal_failure_count');
        $message = $invoice['last_finalization_error']['message']
            ?? $invoice['payment_intent']['last_payment_error']['message']
            ?? 'Automatic subscription payment failed.';

        $isPendingUpgradeInvoice = $record->pending_platform_subscription_plan_id
            && ! $record->pending_plan_effective_at;

        if ($isPendingUpgradeInvoice) {
            $record->update([
                'last_renewal_attempt_at' => now(),
                'last_renewal_error' => $message,
            ]);
        } else {
            $record->update([
                'status' => 'past_due',
                'last_renewal_attempt_at' => now(),
                'last_renewal_error' => $message,
            ]);
        }

        $this->recordPayment($record->fresh(), $invoice, 'failed', $message);
    }

    private function syncScheduleLifecycle(array $schedule): void
    {
        $scheduleId = (string) ($schedule['id'] ?? '');
        $subscriptionId = $this->stripeId($schedule['subscription'] ?? null);

        $record = CompanySubscription::query()
            ->when($scheduleId !== '', fn ($query) => $query->where('stripe_subscription_schedule_id', $scheduleId))
            ->when($scheduleId === '' && $subscriptionId, fn ($query) => $query->where('stripe_subscription_id', $subscriptionId))
            ->first();

        if (! $record) {
            return;
        }

        $record->update(['stripe_subscription_schedule_id' => null]);
    }

    private function recordPayment(CompanySubscription $record, array $invoice, string $status, ?string $failure = null): void
    {
        SubscriptionPayment::updateOrCreate(
            ['provider' => 'stripe', 'provider_invoice_id' => $invoice['id'] ?? null],
            [
                'company_id' => $record->company_id,
                'company_subscription_id' => $record->id,
                'platform_subscription_plan_id' => $record->platform_subscription_plan_id,
                'provider_payment_id' => $this->stripeId($invoice['payment_intent'] ?? null)
                    ?? $this->stripeId($invoice['charge'] ?? null),
                'provider_customer_id' => $this->stripeId($invoice['customer'] ?? null) ?: $record->stripe_customer_id,
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

    private function subscriptionFromInvoice(array $invoice): ?CompanySubscription
    {
        $subscriptionId = $this->stripeId($invoice['subscription'] ?? null);
        return $subscriptionId ? CompanySubscription::where('stripe_subscription_id', $subscriptionId)->first() : null;
    }

    private function planFromSubscription(array $subscription): ?PlatformSubscriptionPlan
    {
        $price = data_get($subscription, 'items.data.0.price');
        $priceId = is_array($price) ? (string) ($price['id'] ?? '') : (string) ($price ?? '');

        return $priceId !== '' ? PlatformSubscriptionPlan::where('stripe_price_id', $priceId)->first() : null;
    }

    private function planFromInvoice(array $invoice, CompanySubscription $record): ?PlatformSubscriptionPlan
    {
        $priceIds = collect($invoice['lines']['data'] ?? [])
            ->map(function ($line): string {
                $price = is_array($line) ? ($line['price'] ?? null) : null;
                return is_array($price) ? (string) ($price['id'] ?? '') : (string) ($price ?? '');
            })
            ->filter()
            ->unique();

        if ($record->pending_platform_subscription_plan_id) {
            $pending = PlatformSubscriptionPlan::find($record->pending_platform_subscription_plan_id);
            if ($pending?->stripe_price_id && $priceIds->contains((string) $pending->stripe_price_id)) {
                return $pending;
            }
        }

        return PlatformSubscriptionPlan::whereIn('stripe_price_id', $priceIds)->orderByDesc('billing_rank')->first();
    }

    private function findSubscription(array $subscription): ?CompanySubscription
    {
        $companyId = (int) ($subscription['metadata']['company_id'] ?? 0);

        return CompanySubscription::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when(! $companyId, fn ($query) => $query->where('stripe_subscription_id', $subscription['id'] ?? ''))
            ->first();
    }

    private function stripeId(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }

        $value = is_scalar($value) ? (string) $value : '';
        return $value !== '' ? $value : null;
    }
}
