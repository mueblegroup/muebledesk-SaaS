<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Invoice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWebhookCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('stripe/platform/webhook')) {
            return $next($request);
        }

        $invoiceId = match (true) {
            $request->is('stripe/webhook') => $this->stripeInvoiceId($request),
            $request->is('hitpay/webhook') => $this->hitPayInvoiceId($request),
            default => null,
        };

        if ($invoiceId) {
            $companyId = Invoice::withoutGlobalScopes()->whereKey($invoiceId)->value('company_id');
            $company = $companyId ? Company::query()->find($companyId) : null;

            if ($company) {
                app()->instance(Company::class, $company);
                app()->instance('currentCompany', $company);
                $request->attributes->set('currentCompany', $company);
            }
        }

        return $next($request);
    }

    private function stripeInvoiceId(Request $request): ?string
    {
        $event = json_decode($request->getContent(), true);
        $object = is_array($event) ? ($event['data']['object'] ?? []) : [];

        return isset($object['metadata']['invoice_id'])
            ? (string) $object['metadata']['invoice_id']
            : null;
    }

    private function hitPayInvoiceId(Request $request): ?string
    {
        $payload = $request->json()->all() ?: $request->all();
        $reference = $payload['payment_request']['reference_number'] ?? $payload['remark'] ?? null;

        if (! is_string($reference)) {
            return null;
        }

        $parts = explode('-', $reference);
        $invoiceId = $parts[1] ?? null;

        return is_numeric($invoiceId) ? (string) $invoiceId : null;
    }
}
