<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice; // If you want to fetch partial invoice details, but it's optional for a public page

class PublicPaymentController extends Controller
{
    public function confirmation(Request $request)
    {
        // Get the status and reference from the URL query parameters
        $status = $request->query('status');
        $reference = $request->query('reference'); // This is your invoice reference number

        $invoice = null;
        if ($reference) {
            // Optionally, try to fetch the invoice by reference number
            // Be cautious about exposing too much info on a public page
            $parts = explode('-', $reference);
            $invoiceId = $parts[1] ?? null;
            if ($invoiceId) {
                // Fetch basic invoice details (e.g., invoice number) if needed for the message
                // Or just rely on the reference number
                $invoice = Invoice::select('id', 'invoice_number', 'status')->find($invoiceId);
            }
        }

        // Pass these to the view
        return view('public.payment_confirmation', compact('status', 'reference', 'invoice'));
    }
}