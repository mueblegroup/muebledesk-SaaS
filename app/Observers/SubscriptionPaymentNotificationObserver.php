<?php

namespace App\Observers;

use App\Models\SubscriptionPayment;
use App\Services\BillingActivityNotifier;

class SubscriptionPaymentNotificationObserver
{
    public function saved(SubscriptionPayment $payment): void
    {
        if ($payment->provider !== 'stripe') {
            return;
        }

        // The checkout success callback stores a cs_... row and Stripe later
        // delivers the authoritative in_... invoice through the webhook. Only
        // notify from invoice-backed records so purchases are never emailed twice.
        $invoiceId = (string) $payment->provider_invoice_id;
        if (! str_starts_with($invoiceId, 'in_')) {
            return;
        }

        if (! $payment->wasRecentlyCreated && ! $payment->wasChanged('status')) {
            return;
        }

        $payment->loadMissing('company', 'plan');
        $company = $payment->company;
        if (! $company) {
            return;
        }

        $planName = $payment->plan?->name ?? 'Subscription plan';
        $currency = strtoupper((string) $payment->currency);
        $amount = $currency.' '.number_format((float) $payment->amount, 2);

        if ($payment->status === 'paid') {
            app(BillingActivityNotifier::class)->notifyOwners(
                $company,
                'Subscription payment received — '.$company->name,
                'Stripe confirmed a subscription payment for your MuebleDesk workspace.',
                [
                    'Plan' => $planName,
                    'Amount' => $amount,
                    'Invoice' => $invoiceId,
                    'Status' => 'Paid',
                ]
            );

            return;
        }

        if ($payment->status === 'failed') {
            app(BillingActivityNotifier::class)->notifyOwners(
                $company,
                'Subscription payment failed — '.$company->name,
                'Stripe could not complete a subscription payment for your MuebleDesk workspace.',
                [
                    'Plan' => $planName,
                    'Amount due' => $amount,
                    'Invoice' => $invoiceId,
                    'Status' => 'Failed',
                    'Reason' => $payment->failure_message,
                ]
            );
        }
    }
}
