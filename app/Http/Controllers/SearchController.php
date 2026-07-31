<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        $user = Auth::user();
        $results = collect();

        if ($query === '') {
            return view('search.index', compact('query', 'results'));
        }

        if ($user?->isAdmin()) {
            $results = $results->merge(
                User::query()
                    ->where(function (Builder $builder) use ($query) {
                        $builder->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    })
                    ->limit(12)
                    ->get()
                    ->map(fn ($item) => [
                        'type' => 'User',
                        'title' => $item->name,
                        'subtitle' => $item->email,
                        'url' => route('users.index', ['q' => $item->email]),
                    ])
            );
        }

        if ($user?->isAdmin() || $user?->isEmployee()) {
            $clientQuery = $this->clientSearchQuery($query);
            $invoiceQuery = $this->invoiceSearchQuery($query);
            $quotationQuery = $this->quotationSearchQuery($query);

            if ($user?->isEmployee()) {
                $clientQuery->where('employee_id', $user->id);
                $invoiceQuery->where('employee_id', $user->id);
                $quotationQuery->where('employee_id', $user->id);
            }

            $results = $results->merge(
                $clientQuery->limit(15)->get()->map(fn ($item) => [
                    'type' => 'Client',
                    'title' => $item->name,
                    'subtitle' => collect([$item->email, $item->phone, $item->tin_number])->filter()->implode(' · '),
                    'url' => route('clients.show', $item),
                ])
            );

            $results = $results->merge(
                $invoiceQuery->limit(25)->get()->map(fn ($item) => [
                    'type' => 'Invoice',
                    'title' => $item->invoice_number,
                    'subtitle' => ($item->client->name ?? 'No client').' · '.$item->status.' · RM '.number_format((float) $item->total_amount, 2),
                    'url' => route('invoices.show', $item),
                ])
            );

            $results = $results->merge(
                $quotationQuery->limit(25)->get()->map(fn ($item) => [
                    'type' => 'Quotation',
                    'title' => $item->quote_number,
                    'subtitle' => ($item->client->name ?? 'No client').' · '.$item->status.' · RM '.number_format((float) $item->total_amount, 2),
                    'url' => route('quotations.show', $item),
                ])
            );
        }

        if ($user?->isCustomer()) {
            $client = $user->client ?? $user->clients ?? null;
            if ($client) {
                $results = $results->merge(
                    $this->invoiceSearchQuery($query)
                        ->where('client_id', $client->id)
                        ->limit(25)
                        ->get()
                        ->map(fn ($item) => [
                            'type' => 'Invoice',
                            'title' => $item->invoice_number,
                            'subtitle' => $item->status.' · RM '.number_format((float) $item->total_amount, 2),
                            'url' => route('invoices.customer_show', $item),
                        ])
                );
            }
        }

        return view('search.index', [
            'query' => $query,
            'results' => $results->unique(fn ($item) => $item['type'].'|'.$item['url'])->take(75),
        ]);
    }

    private function clientSearchQuery(string $query): Builder
    {
        return Client::query()->where(fn (Builder $client) => $this->applyClientSearch($client, $query));
    }

    private function invoiceSearchQuery(string $query): Builder
    {
        $numeric = $this->normaliseNumericSearch($query);

        return Invoice::query()
            ->with('client')
            ->where(function (Builder $builder) use ($query, $numeric) {
                $builder->where('invoice_number', 'like', "%{$query}%")
                    ->orWhere('status', 'like', "%{$query}%")
                    ->orWhere('date', 'like', "%{$query}%")
                    ->orWhere('due_date', 'like', "%{$query}%")
                    ->orWhere('discount_type', 'like', "%{$query}%")
                    ->orWhere('tax_type', 'like', "%{$query}%")
                    ->orWhere('payment_link', 'like', "%{$query}%")
                    ->orWhereHas('employee', function (Builder $employee) use ($query) {
                        $employee->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    })
                    ->orWhereHas('client', fn (Builder $client) => $this->applyClientSearch($client, $query))
                    ->orWhereHas('items', function (Builder $item) use ($query, $numeric) {
                        $item->where('item_name', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%");

                        if ($numeric !== null) {
                            $item->orWhere('quantity', $numeric)
                                ->orWhere('price', $numeric)
                                ->orWhere('total', $numeric);
                        }
                    })
                    ->orWhereHas('payments', function (Builder $payment) use ($query, $numeric) {
                        $payment->where('payment_method', 'like', "%{$query}%")
                            ->orWhere('transaction_reference', 'like', "%{$query}%")
                            ->orWhere('notes', 'like', "%{$query}%")
                            ->orWhere('payment_date', 'like', "%{$query}%");

                        if ($numeric !== null) {
                            $payment->orWhere('amount', $numeric);
                        }
                    });

                if ($numeric !== null) {
                    $builder->orWhere('sub_total', $numeric)
                        ->orWhere('discount_value', $numeric)
                        ->orWhere('discount_amount', $numeric)
                        ->orWhere('tax_rate', $numeric)
                        ->orWhere('tax_amount', $numeric)
                        ->orWhere('total_amount', $numeric)
                        ->orWhere('amount_paid', $numeric);
                }
            });
    }

    private function quotationSearchQuery(string $query): Builder
    {
        $numeric = $this->normaliseNumericSearch($query);

        return Quotation::query()
            ->with('client')
            ->where(function (Builder $builder) use ($query, $numeric) {
                $builder->where('quote_number', 'like', "%{$query}%")
                    ->orWhere('status', 'like', "%{$query}%")
                    ->orWhere('date', 'like', "%{$query}%")
                    ->orWhere('expiry_date', 'like', "%{$query}%")
                    ->orWhere('discount_type', 'like', "%{$query}%")
                    ->orWhere('tax_type', 'like', "%{$query}%")
                    ->orWhereHas('employee', function (Builder $employee) use ($query) {
                        $employee->where('name', 'like', "%{$query}%")
                            ->orWhere('email', 'like', "%{$query}%");
                    })
                    ->orWhereHas('client', fn (Builder $client) => $this->applyClientSearch($client, $query))
                    ->orWhereHas('items', function (Builder $item) use ($query, $numeric) {
                        $item->where('item_name', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%");

                        if ($numeric !== null) {
                            $item->orWhere('quantity', $numeric)
                                ->orWhere('price', $numeric)
                                ->orWhere('total', $numeric);
                        }
                    });

                if ($numeric !== null) {
                    $builder->orWhere('sub_total', $numeric)
                        ->orWhere('discount_value', $numeric)
                        ->orWhere('discount_amount', $numeric)
                        ->orWhere('tax_rate', $numeric)
                        ->orWhere('tax_amount', $numeric)
                        ->orWhere('total_amount', $numeric);
                }
            });
    }

    private function applyClientSearch(Builder $client, string $query): void
    {
        $client->where('name', 'like', "%{$query}%")
            ->orWhere('client_type', 'like', "%{$query}%")
            ->orWhere('contact_person', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('billing_email', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('website', 'like', "%{$query}%")
            ->orWhere('address', 'like', "%{$query}%")
            ->orWhere('address_line_1', 'like', "%{$query}%")
            ->orWhere('address_line_2', 'like', "%{$query}%")
            ->orWhere('city', 'like', "%{$query}%")
            ->orWhere('state', 'like', "%{$query}%")
            ->orWhere('postcode', 'like', "%{$query}%")
            ->orWhere('country_code', 'like', "%{$query}%")
            ->orWhere('tin_number', 'like', "%{$query}%")
            ->orWhere('id_type', 'like', "%{$query}%")
            ->orWhere('id_number', 'like', "%{$query}%")
            ->orWhere('sst_registration_number', 'like', "%{$query}%")
            ->orWhere('payment_terms_days', 'like', "%{$query}%")
            ->orWhere('notes', 'like', "%{$query}%");
    }

    private function normaliseNumericSearch(string $query): ?float
    {
        $normalised = preg_replace('/[^0-9.\-]/', '', $query);

        return $normalised !== '' && is_numeric($normalised)
            ? (float) $normalised
            : null;
    }
}
