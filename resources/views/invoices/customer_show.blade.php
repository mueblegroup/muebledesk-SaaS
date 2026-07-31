<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Invoice Details') }}
        </h2>
    </x-slot>

    @php
        $amountDue = max(0, (float) ($invoice->total_amount ?? 0) - (float) ($invoice->amount_paid ?? 0));
        $isFullyPaid = (float) ($invoice->total_amount ?? 0) > 0 && $amountDue <= 0;
        $eInvoice = $invoice->eInvoice;
    @endphp

    <div class="py-8 sm:py-12">
        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-3xl bg-white shadow-sm dark:bg-slate-900">
                <div class="p-4 text-gray-900 dark:text-slate-100 sm:p-6">
                    <div class="mb-6 grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                        <h3 class="min-w-0 break-words text-lg font-bold text-gray-900 dark:text-white">Invoice #{{ $invoice->invoice_number ?? 'N/A' }}</h3>
                        <div class="document-action-row min-w-0 max-w-full lg:justify-end">
                            @if ($amountDue > 0 && $invoice->payment_link)
                                <a href="{{ $invoice->payment_link }}" target="_blank" rel="noopener noreferrer" class="btn-primary max-w-full whitespace-normal text-center break-words">
                                    Pay RM {{ number_format($amountDue, 2) }} with Stripe
                                </a>
                            @endif
                            <a href="{{ route('invoices.customer_download', $invoice) }}" class="btn-success max-w-full text-center">Download PDF</a>
                            <a href="{{ route('invoices.customer_index') }}" class="btn-secondary max-w-full text-center">Back to My Invoices</a>
                        </div>
                    </div>

                    @if ($amountDue > 0)
                        @if ($invoice->payment_link)
                            <div class="mb-6 max-w-full overflow-hidden rounded-3xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900 dark:bg-indigo-950/50 sm:p-5">
                                <div class="grid min-w-0 gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                    <div class="min-w-0">
                                        <h4 class="break-words font-extrabold text-indigo-950 dark:text-indigo-200">Online payment available</h4>
                                        <p class="mt-1 break-words text-sm text-indigo-800 dark:text-indigo-300">Pay the outstanding balance securely through Stripe Checkout.</p>
                                    </div>
                                    <a href="{{ $invoice->payment_link }}" target="_blank" rel="noopener noreferrer" class="btn-primary w-full max-w-full text-center sm:w-auto">
                                        Pay Now
                                    </a>
                                </div>
                            </div>
                        @else
                            <div class="mb-6 max-w-full overflow-hidden rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300">
                                Online payment is not available for this invoice yet. Please contact the issuer.
                            </div>
                        @endif
                    @endif

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><p class="text-sm font-medium text-gray-600 dark:text-slate-400">Issue Date:</p><p class="mt-1 text-lg">{{ $invoice->date?->format('Y-m-d') ?? 'N/A' }}</p></div>
                        <div><p class="text-sm font-medium text-gray-600 dark:text-slate-400">Due Date:</p><p class="mt-1 text-lg">{{ $invoice->due_date?->format('Y-m-d') ?? 'N/A' }}</p></div>
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-slate-400">Status:</p>
                            <p class="mt-1 text-lg"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full @if($invoice->status == 'paid') bg-green-100 text-green-800 @elseif($invoice->status == 'overdue') bg-red-100 text-red-800 @else bg-yellow-100 text-yellow-800 @endif">{{ Str::title(str_replace('_', ' ', $invoice->status ?? 'N/A')) }}</span></p>
                        </div>
                        <div><p class="text-sm font-medium text-gray-600 dark:text-slate-400">Sub-Total:</p><p class="mt-1 text-lg">RM {{ number_format($invoice->sub_total ?? 0, 2) }}</p></div>
                        <div><p class="text-sm font-medium text-gray-600 dark:text-slate-400">Total Amount:</p><p class="mt-1 text-lg font-bold">RM {{ number_format($invoice->total_amount ?? 0, 2) }}</p></div>
                        <div><p class="text-sm font-medium text-gray-600 dark:text-slate-400">Amount Paid:</p><p class="mt-1 text-lg font-bold text-green-600">RM {{ number_format($invoice->amount_paid ?? 0, 2) }}</p></div>
                        <div><p class="text-sm font-medium text-gray-600 dark:text-slate-400">Amount Due:</p><p class="mt-1 text-lg font-bold text-red-600">RM {{ number_format($amountDue, 2) }}</p></div>
                    </div>

                    <div class="mt-8 max-w-full overflow-hidden rounded-3xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950/50">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div class="min-w-0">
                                <h4 class="break-words text-lg font-extrabold text-indigo-950 dark:text-indigo-200">My e-Invoice</h4>
                                @if ($eInvoice)
                                    <p class="mt-1 break-words text-sm text-indigo-800 dark:text-indigo-300">Current status: <strong>{{ strtoupper($eInvoice->status) }}</strong></p>
                                @elseif ($isFullyPaid)
                                    <p class="mt-1 break-words text-sm text-indigo-800 dark:text-indigo-300">This invoice is fully paid and can be submitted directly to MyInvois.</p>
                                @else
                                    <p class="mt-1 break-words text-sm text-indigo-800 dark:text-indigo-300">e-Invoice submission becomes available after full payment.</p>
                                @endif
                            </div>
                            <div class="flex max-w-full flex-wrap gap-3">
                                <a href="{{ route('customer.einvoice-profile.edit') }}" class="inline-flex max-w-full rounded-xl bg-white px-4 py-2 text-center text-xs font-bold text-indigo-700 shadow-sm">Update profile</a>
                                @if ($isFullyPaid)
                                    <a href="{{ route('customer.einvoices.preview', $invoice) }}" class="inline-flex max-w-full rounded-xl bg-indigo-600 px-4 py-2 text-center text-xs font-bold text-white hover:bg-indigo-500">
                                        {{ $eInvoice?->status === 'valid' ? 'View e-Invoice QR' : ($eInvoice ? 'View e-Invoice Status' : 'Generate My e-Invoice') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <h4 class="text-md mt-8 mb-4 font-semibold text-gray-800 dark:text-white">Invoice Items</h4>
                    @if ($invoice->items->isEmpty())
                        <p class="text-gray-600 dark:text-slate-400">No items found for this invoice.</p>
                    @else
                        <div class="mb-6 max-w-full overflow-x-auto">
                            <table>
                                <thead><tr><th>Item Name</th><th>Description</th><th>Quantity</th><th>Price</th><th class="text-right">Total</th></tr></thead>
                                <tbody>
                                    @foreach ($invoice->items as $item)
                                        <tr>
                                            <td class="font-medium">{{ $item->item_name }}</td>
                                            <td>@if($item->description)<div class="document-rich-text">{!! $item->description !!}</div>@else N/A @endif</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>RM {{ number_format($item->price, 2) }}</td>
                                            <td class="text-right">RM {{ number_format($item->total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <h4 class="text-md mt-8 mb-4 font-semibold text-gray-800 dark:text-white">Payment History</h4>
                    @if ($invoice->payments->isEmpty())
                        <p class="text-gray-600 dark:text-slate-400">No payments recorded for this invoice yet.</p>
                    @else
                        <div class="max-w-full overflow-x-auto">
                            <table>
                                <thead><tr><th>Payment ID</th><th>Amount</th><th>Date</th><th>Method</th><th>Reference</th></tr></thead>
                                <tbody>
                                    @foreach ($invoice->payments as $payment)
                                        <tr><td>{{ $payment->id }}</td><td>RM {{ number_format($payment->amount, 2) }}</td><td>{{ $payment->payment_date->format('Y-m-d') }}</td><td>{{ Str::title(str_replace('_', ' ', $payment->payment_method)) }}</td><td class="max-w-xs break-all">{{ $payment->transaction_reference ?? 'N/A' }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
