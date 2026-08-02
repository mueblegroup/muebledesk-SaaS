<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PublicPaymentController extends Controller
{
    public function confirmation(Request $request)
    {
        $status = $request->query('status');
        $reference = $request->query('reference');

        // Never resolve an invoice from a predictable public ID. The confirmation
        // page only echoes the gateway status/reference and exposes no tenant data.
        return view('public.payment_confirmation', [
            'status' => $status,
            'reference' => $reference,
            'invoice' => null,
        ]);
    }
}
