<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerEInvoiceProfileController extends Controller
{
    public function edit()
    {
        $client = Auth::user()?->clients;

        abort_unless($client, 404, 'No client profile is linked to this account.');

        return view('customer.einvoice-profile', compact('client'));
    }

    public function update(Request $request)
    {
        $client = Auth::user()?->clients;

        abort_unless($client, 404, 'No client profile is linked to this account.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'billing_email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'tin_number' => 'required|string|max:100',
            'id_type' => ['required', 'string', Rule::in(['NRIC', 'PASSPORT', 'ARMY'])],
            'id_number' => 'required|string|max:100',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => ['required', 'string', Rule::in(array_map(fn ($n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT), range(1, 17)))],
            'postcode' => 'required|string|max:20',
            'country_code' => ['required', 'string', Rule::in(['MY', 'MYS'])],
        ]);

        $validated['client_type'] = 'individual';
        // Keep the existing alpha-2 database value. UBL generation normalizes MY to MYS.
        $validated['country_code'] = 'MY';
        $validated['billing_email'] = $validated['billing_email'] ?: $client->email;
        $validated['address'] = collect([
            $validated['address_line_1'],
            $validated['address_line_2'] ?? null,
            trim($validated['postcode'].' '.$validated['city']),
            $validated['state'],
            'MY',
        ])->filter()->implode("\n");

        $client->update($validated);
        Auth::user()->update(['name' => $validated['name']]);

        return back()->with('success', 'Your e-Invoice profile has been updated.');
    }
}
