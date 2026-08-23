<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class PublicDocumentController extends Controller
{
    public function invoice(Invoice $invoice)
    {
        $this->bindCompany($invoice->company);
        $invoice->load('client', 'employee', 'items', 'payments');

        return Pdf::loadView('pdfs.invoice', [
            'invoice' => $invoice,
            'settings' => Setting::allKeyed(),
        ])->stream('invoice_'.$invoice->invoice_number.'.pdf');
    }

    public function quotation(Quotation $quotation)
    {
        $this->bindCompany($quotation->company);
        $quotation->load('client', 'employee', 'items');

        return Pdf::loadView('pdfs.quotation', [
            'quotation' => $quotation,
            'settings' => Setting::allKeyed(),
        ])->stream('quotation_'.$quotation->quote_number.'.pdf');
    }

    public function payment(Payment $payment)
    {
        $this->bindCompany($payment->company);
        $payment->load('invoice.client', 'invoice.employee', 'recordedBy', 'receipt');
        abort_unless($payment->receipt, 404, 'Payment receipt not found.');

        return Pdf::loadView('pdfs.payment-receipt', [
            'payment' => $payment,
            'receipt' => $payment->receipt,
            'settings' => Setting::allKeyed(),
        ])->stream('receipt_'.$payment->receipt->receipt_number.'.pdf');
    }

    private function bindCompany($company): void
    {
        abort_unless($company, 404);
        app()->instance('currentCompany', $company);
    }
}
