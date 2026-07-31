<?php

namespace App\Services\MyInvois;

use App\Models\Invoice;

class InvoiceReadiness
{
    public function __construct(private readonly SupplierProfile $supplierProfile)
    {
    }

    public function check(Invoice $invoice): array
    {
        $invoice->loadMissing('client', 'items');
        $client = $invoice->client;
        $supplier = $this->supplierProfile->get();

        // Keep the existing UBL builder compatible while supplier identity moves to DB settings.
        config(['myinvois.supplier' => $supplier]);

        $errors = [];
        $requiredSupplier = [
            'tin' => 'Supplier TIN',
            'registration_type' => 'Supplier registration type',
            'registration_number' => 'Supplier registration number',
            'msic_code' => 'Supplier MSIC code',
            'business_activity' => 'Supplier business activity',
            'name' => 'Supplier name',
            'phone' => 'Supplier phone',
            'address_line_1' => 'Supplier address',
            'city' => 'Supplier city',
            'state_code' => 'Supplier state code',
            'postcode' => 'Supplier postcode',
            'country_code' => 'Supplier country code',
        ];

        foreach ($requiredSupplier as $key => $label) {
            if (blank($supplier[$key] ?? null)) {
                $errors[] = $label.' is missing from Admin > Settings > MyInvois e-Invoice Supplier Profile.';
            }
        }

        if (! blank($supplier['state_code'] ?? null) && ! StateCode::isValid($supplier['state_code'], $supplier['country_code'] ?? null)) {
            $errors[] = 'Supplier state must be a valid MyInvois state code or recognised Malaysian state name.';
        }

        if (! $client) {
            $errors[] = 'Invoice has no client.';
        } else {
            foreach ([
                'name' => 'Buyer name',
                'tin_number' => 'Buyer TIN',
                'id_type' => 'Buyer ID type',
                'id_number' => 'Buyer ID number',
                'phone' => 'Buyer phone',
                'address_line_1' => 'Buyer address',
                'city' => 'Buyer city',
                'state' => 'Buyer state code',
                'postcode' => 'Buyer postcode',
                'country_code' => 'Buyer country code',
            ] as $key => $label) {
                if (blank($client->{$key})) {
                    $errors[] = $label.' is missing.';
                }
            }

            if (! blank($client->state) && ! StateCode::isValid($client->state, $client->country_code)) {
                $errors[] = 'Buyer state must be a valid MyInvois state code or recognised Malaysian state name.';
            }
        }

        if ($invoice->items->isEmpty()) {
            $errors[] = 'Invoice must contain at least one item.';
        }

        foreach ($invoice->items as $index => $item) {
            if (blank($item->item_name)) $errors[] = 'Item '.($index + 1).' has no name.';
            if ((float) $item->quantity <= 0) $errors[] = 'Item '.($index + 1).' quantity must be greater than zero.';
            if ((float) $item->price < 0) $errors[] = 'Item '.($index + 1).' price cannot be negative.';
        }

        if (blank($invoice->invoice_number)) $errors[] = 'Invoice number is missing.';
        if (! $invoice->date) {
            $errors[] = 'Invoice date is missing.';
        } elseif ($invoice->date->isFuture()) {
            $errors[] = 'Invoice date cannot be in the future.';
        } elseif ($invoice->date->lt(now()->subHours(71)->startOfDay())) {
            $errors[] = 'Invoice date is outside the permitted e-Invoice submission window. Use a current invoice date or follow the applicable MyInvois late-submission procedure.';
        }

        if ((float) $invoice->total_amount <= 0) {
            $errors[] = 'Invoice total must be greater than zero.';
        } elseif ((float) $invoice->amount_paid < (float) $invoice->total_amount) {
            $errors[] = 'Only fully paid invoices can be submitted as e-Invoices.';
        }

        if (strtoupper((string) config('myinvois.currency', 'MYR')) !== 'MYR') {
            $errors[] = 'This integration currently supports MYR invoices only.';
        }

        return [
            'ready' => $errors === [],
            'errors' => $errors,
            'environment' => config('myinvois.environment', 'sandbox'),
            'document_version' => config('myinvois.document_version', '1.0'),
        ];
    }
}
