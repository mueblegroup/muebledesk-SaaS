<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">{{ __('Payments') }}</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">All Payments</h3>
                <p class="section-subtitle">Search, filter, export, create, share, and correct recorded invoice payments.</p>
            </div>
            <a href="{{ route('payments.create') }}" class="btn-primary">Record Payment</a>
        </div>

        <x-filters-toolbar
            :action="route('payments.index')"
            search-placeholder="Search invoice, client, method, reference, attachment..."
            export-route="payments.export"
            :filters="[
                ['name' => 'payment_method', 'label' => 'Method', 'type' => 'select', 'placeholder' => 'All methods', 'options' => ['hitpay' => 'HitPay', 'stripe' => 'Stripe', 'bank_transfer' => 'Bank Transfer', 'cash' => 'Cash', 'credit_card' => 'Credit Card', 'cheque' => 'Cheque', 'online_payment' => 'Online Payment']],
                ['name' => 'from', 'label' => 'From', 'type' => 'date'],
                ['name' => 'to', 'label' => 'To', 'type' => 'date'],
                ['name' => 'sort', 'label' => 'Sort', 'type' => 'select', 'placeholder' => 'Default', 'options' => ['payment_date' => 'Payment Date', 'amount' => 'Amount', 'payment_method' => 'Method']],
            ]"
        />

        @if($payments->isEmpty())
            <x-empty-state title="No payments found" message="No payment records matched your current search or filters." :action-url="route('payments.create')" action-label="Record Payment" />
        @else
            <div class="overflow-x-auto">
                <table>
                    <thead><tr><th>Invoice #</th><th>Client</th><th>Amount</th><th>Date</th><th>Method</th><th>Reference</th><th>Attachment</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @foreach($payments as $payment)
                            @php
                                $client = $payment->invoice?->client;
                                $receiptNumber = $payment->receipt?->receipt_number ?: $payment->id;
                                $shareUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('shared.payment', now()->addDays(30), ['payment' => $payment]);
                                $shareMessage = 'Payment receipt #'.$receiptNumber.' for invoice #'.($payment->invoice?->invoice_number ?? 'N/A').' from '.(app('currentCompany')->name ?? config('app.name')).'. Amount: RM '.number_format((float) $payment->amount, 2).'.';
                            @endphp
                            <tr>
                                <td class="font-semibold text-slate-950 dark:text-white">@if($payment->invoice)<a href="{{ route('invoices.show', $payment->invoice) }}">{{ $payment->invoice->invoice_number }}</a>@else N/A @endif</td>
                                <td>{{ $client?->name ?? 'N/A' }}</td>
                                <td>RM {{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->payment_date?->format('Y-m-d') ?? 'N/A' }}</td>
                                <td>{{ Str::title(str_replace('_', ' ', $payment->payment_method ?? 'N/A')) }}</td>
                                <td>{{ $payment->transaction_reference ?? 'N/A' }}</td>
                                <td>
                                    @if($payment->transfer_receipt_path)
                                        <a href="{{ asset('storage/'.$payment->transfer_receipt_path) }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ $payment->transfer_receipt_original_name ?: 'View attachment' }}
                                        </a>
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-3">
                                        <a href="{{ route('payments.edit', $payment) }}" class="font-semibold text-indigo-600">Edit</a>
                                        <a href="{{ route('payments.receipt', $payment) }}" class="font-semibold text-indigo-600">Receipt</a>
                                        <x-document-share
                                            :url="$shareUrl"
                                            :title="'Payment receipt #'.$receiptNumber"
                                            :message="$shareMessage"
                                            :phone="$client?->phone"
                                            :email="$client?->billing_email ?: $client?->email"
                                            :country-code="$client?->country_code"
                                        />
                                        <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('Delete this payment and recalculate the invoice balance?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="font-semibold text-red-600">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $payments->links() }}</div>
        @endif
    </div>
</x-app-layout>
