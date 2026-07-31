<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Edit Payment</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h3 class="section-title">Edit Payment</h3>
            <p class="section-subtitle">Update the payment and automatically recalculate the invoice balance and receipt.</p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950">
            <div class="grid gap-4 sm:grid-cols-3">
                <div><span class="block text-xs font-bold uppercase text-slate-400">Invoice</span><strong>{{ $payment->invoice->invoice_number }}</strong></div>
                <div><span class="block text-xs font-bold uppercase text-slate-400">Client</span><strong>{{ $payment->invoice->client->name ?? 'N/A' }}</strong></div>
                <div><span class="block text-xs font-bold uppercase text-slate-400">Invoice Total</span><strong>RM {{ number_format($payment->invoice->total_amount, 2) }}</strong></div>
            </div>
        </div>

        <form method="POST" action="{{ route('payments.update', $payment) }}" enctype="multipart/form-data" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="amount" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Amount</label>
                    <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $payment->amount) }}" class="block w-full" required>
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <label for="payment_date" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Payment Date</label>
                    <input id="payment_date" name="payment_date" type="date" max="{{ now()->format('Y-m-d') }}" value="{{ old('payment_date', $payment->payment_date?->format('Y-m-d')) }}" class="block w-full" required>
                    <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                </div>
                <div>
                    <label for="payment_method" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="block w-full" required>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method }}" @selected(old('payment_method', $payment->payment_method) === $method)>{{ Str::title(str_replace('_', ' ', $method)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="transaction_reference" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Reference</label>
                    <input id="transaction_reference" name="transaction_reference" type="text" value="{{ old('transaction_reference', $payment->transaction_reference) }}" class="block w-full" maxlength="255">
                </div>
                <div>
                    <label for="transaction_id" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Transaction ID</label>
                    <input id="transaction_id" name="transaction_id" type="text" value="{{ old('transaction_id', $payment->transaction_id) }}" class="block w-full" maxlength="255">
                </div>
                <div>
                    <label for="transfer_receipt" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Replace Attachment</label>
                    <input id="transfer_receipt" name="transfer_receipt" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="block w-full">
                    <p class="mt-1 text-xs text-slate-500">PDF or image, maximum 5 MB.</p>
                </div>
            </div>

            @if($payment->transfer_receipt_path)
                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 text-sm font-semibold dark:border-slate-700">
                    <input type="checkbox" name="remove_transfer_receipt" value="1">
                    Remove current attachment: {{ $payment->transfer_receipt_original_name ?: 'attachment' }}
                </label>
            @endif

            <div>
                <label for="notes" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Notes</label>
                <textarea id="notes" name="notes" rows="4" class="block w-full">{{ old('notes', $payment->notes) }}</textarea>
            </div>

            <label class="flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="is_deposit" value="1" @checked(old('is_deposit', $payment->is_deposit))>
                Mark as deposit
            </label>

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                <a href="{{ route('payments.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</x-app-layout>
