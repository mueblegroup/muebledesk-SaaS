<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Record Payment</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h3 class="section-title">Manual Payment</h3>
            <p class="section-subtitle">Record cash, bank transfer, cheque, card, or other payments against an unpaid invoice.</p>
        </div>

        <form method="POST" action="{{ $selectedInvoice ? route('invoices.payments.store', $selectedInvoice) : route('payments.store') }}" enctype="multipart/form-data" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf

            <div>
                <label for="invoice_id" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Invoice</label>
                <select id="invoice_id" name="invoice_id" class="block w-full" {{ $selectedInvoice ? 'disabled' : '' }} required>
                    <option value="">Select an unpaid invoice</option>
                    @foreach($invoices as $invoice)
                        @php $outstanding = max(0, (float) $invoice->total_amount - (float) $invoice->amount_paid); @endphp
                        <option value="{{ $invoice->id }}" @selected(old('invoice_id', $selectedInvoice?->id) == $invoice->id)>
                            {{ $invoice->invoice_number }} — {{ $invoice->client->name ?? 'N/A' }} — RM {{ number_format($outstanding, 2) }} outstanding
                        </option>
                    @endforeach
                </select>
                @if($selectedInvoice)<input type="hidden" name="invoice_id" value="{{ $selectedInvoice->id }}">@endif
                <x-input-error :messages="$errors->get('invoice_id')" class="mt-2" />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="amount" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Amount</label>
                    <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $selectedInvoice ? max(0, (float) $selectedInvoice->total_amount - (float) $selectedInvoice->amount_paid) : '') }}" class="block w-full" required>
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <label for="payment_date" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Payment Date</label>
                    <input id="payment_date" name="payment_date" type="date" max="{{ now()->format('Y-m-d') }}" value="{{ old('payment_date', now()->format('Y-m-d')) }}" class="block w-full" required>
                    <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                </div>
                <div>
                    <label for="payment_method" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="block w-full" required>
                        <option value="">Select method</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ Str::title(str_replace('_', ' ', $method)) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                </div>
                <div>
                    <label for="transaction_reference" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Reference</label>
                    <input id="transaction_reference" name="transaction_reference" type="text" value="{{ old('transaction_reference') }}" class="block w-full" maxlength="255">
                </div>
                <div>
                    <label for="transaction_id" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Transaction ID</label>
                    <input id="transaction_id" name="transaction_id" type="text" value="{{ old('transaction_id') }}" class="block w-full" maxlength="255">
                </div>
                <div>
                    <label for="transfer_receipt" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Payment Attachment</label>
                    <input id="transfer_receipt" name="transfer_receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="block w-full">
                    <p class="mt-1 text-xs text-slate-500">PDF or image, maximum 5 MB.</p>
                </div>
            </div>

            <div>
                <label for="notes" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Notes</label>
                <textarea id="notes" name="notes" rows="4" class="block w-full">{{ old('notes') }}</textarea>
            </div>

            <label class="flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="is_deposit" value="1" @checked(old('is_deposit'))>
                Mark as deposit
            </label>

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                <a href="{{ route('payments.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</x-app-layout>
