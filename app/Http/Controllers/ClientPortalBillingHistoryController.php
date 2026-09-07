<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SubscriptionPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalBillingHistoryController extends Controller
{
    public function index(Request $request, Company $company): View
    {
        $this->authorizeCompany($request, $company);

        $payments = SubscriptionPayment::query()
            ->with('plan')
            ->where('company_id', $company->id)
            ->where(function ($query): void {
                // Subscription checkout creates a temporary cs_* confirmation row.
                // Stripe invoice webhooks create the canonical in_* billing record,
                // which carries the real Stripe invoice URLs. Hide the temporary row
                // so customers never see the same purchase twice.
                $query->where('provider', '!=', 'stripe')
                    ->orWhere(function ($stripeQuery): void {
                        $stripeQuery->where('provider', 'stripe')
                            ->where('provider_invoice_id', 'like', 'in_%');
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('client-portal.billing-history', [
            'company' => $company,
            'payments' => $payments,
        ]);
    }

    public function invoice(Request $request, Company $company, SubscriptionPayment $payment): RedirectResponse
    {
        $this->authorizePayment($request, $company, $payment);

        $metadata = $payment->metadata ?? [];
        $url = $metadata['hosted_invoice_url'] ?? $metadata['invoice_pdf'] ?? null;

        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            return back()->with('error', 'The Stripe invoice document is not available for this billing record.');
        }

        return redirect()->away($url);
    }

    public function receipt(Request $request, Company $company, SubscriptionPayment $payment)
    {
        $this->authorizePayment($request, $company, $payment);
        abort_unless($payment->status === 'paid', 404);

        $company->loadMissing('owners');
        $payment->loadMissing('plan');

        return Pdf::loadView('client-portal.billing-receipt-pdf', [
            'company' => $company,
            'payment' => $payment,
        ])->download('muebledesk-payment-receipt-'.$payment->id.'.pdf');
    }

    private function authorizePayment(Request $request, Company $company, SubscriptionPayment $payment): void
    {
        $this->authorizeCompany($request, $company);
        abort_unless((int) $payment->company_id === (int) $company->id, 404);
    }

    private function authorizeCompany(Request $request, Company $company): void
    {
        $membership = $request->user()->companies()->whereKey($company->id)->first();
        abort_unless($membership, 403);
        abort_unless(in_array($membership->pivot->role, ['owner', 'admin'], true), 403);
    }
}
