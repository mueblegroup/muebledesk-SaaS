<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\Quotation;
use App\Models\Setting;
use App\Services\DocumentNumberGenerator;
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
        $receipt = $this->ensureReceipt($payment);

        return Pdf::loadView('pdfs.payment-receipt', [
            'payment' => $payment,
            'receipt' => $receipt,
            'settings' => Setting::allKeyed(),
        ])->stream('receipt_'.$receipt->receipt_number.'.pdf');
    }

    private function ensureReceipt(Payment $payment): PaymentReceipt
    {
        return PaymentReceipt::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'receipt_number' => app(DocumentNumberGenerator::class)->generate(
                    new PaymentReceipt,
                    'receipt_number',
                    'receipt_prefix',
                    'receipt_number_format',
                    'REC',
                    $payment->payment_date,
                    (int) ($payment->recorded_by_employee_id ?? $payment->invoice?->employee_id ?? 0),
                    'receipt_number'
                ),
                'date' => $payment->payment_date,
                'amount' => $payment->amount,
            ]
        );
    }

    private function bindCompany($company): void
    {
        abort_unless($company, 404);
        app()->instance('currentCompany', $company);
    }
}
