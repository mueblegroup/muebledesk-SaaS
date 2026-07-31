@csrf

<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
    <div>
        <label for="expense_date" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Expense Date <span class="text-red-600">*</span></label>
        <input id="expense_date" name="expense_date" type="date" value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required class="block w-full">
        <x-input-error :messages="$errors->get('expense_date')" class="mt-2" />
    </div>

    <div>
        <label for="category" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Category <span class="text-red-600">*</span></label>
        <select id="category" name="category" required class="block w-full">
            <option value="">Select category</option>
            @foreach ($categories as $value => $label)
                <option value="{{ $value }}" @selected(old('category', $expense->category) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category')" class="mt-2" />
    </div>

    <div>
        <label for="amount" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Amount <span class="text-red-600">*</span></label>
        <input id="amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount', $expense->amount) }}" required class="block w-full">
        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
    </div>

    <div>
        <label for="vendor" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Vendor / Payee</label>
        <input id="vendor" name="vendor" type="text" value="{{ old('vendor', $expense->vendor) }}" class="block w-full" placeholder="e.g. AWS, Adobe, contractor name">
        <x-input-error :messages="$errors->get('vendor')" class="mt-2" />
    </div>

    <div>
        <label for="payment_method" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Payment Method</label>
        <select id="payment_method" name="payment_method" class="block w-full">
            @foreach (['' => 'Select method', 'bank_transfer' => 'Bank Transfer', 'cash' => 'Cash', 'card' => 'Card', 'online_payment' => 'Online Payment', 'cheque' => 'Cheque', 'other' => 'Other'] as $value => $label)
                <option value="{{ $value }}" @selected(old('payment_method', $expense->payment_method) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
    </div>

    <div>
        <label for="reference_number" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Reference Number</label>
        <input id="reference_number" name="reference_number" type="text" value="{{ old('reference_number', $expense->reference_number) }}" class="block w-full" placeholder="Receipt / transaction reference">
        <x-input-error :messages="$errors->get('reference_number')" class="mt-2" />
    </div>

    <div class="md:col-span-2 xl:col-span-3">
        <label for="description" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Description <span class="text-red-600">*</span></label>
        <input id="description" name="description" type="text" value="{{ old('description', $expense->description) }}" required class="block w-full" placeholder="What was this spend for?">
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <div>
        <label for="currency" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Currency</label>
        <input id="currency" name="currency" type="text" value="{{ old('currency', $expense->currency ?: 'MYR') }}" class="block w-full">
        <x-input-error :messages="$errors->get('currency')" class="mt-2" />
    </div>

    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
        <input id="is_billable" name="is_billable" type="checkbox" value="1" @checked(old('is_billable', $expense->is_billable))>
        <label for="is_billable" class="text-sm font-semibold text-slate-700 dark:text-slate-200">Billable / rechargeable to client</label>
    </div>

    <div class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
        <input id="is_tax_deductible" name="is_tax_deductible" type="checkbox" value="1" @checked(old('is_tax_deductible', $expense->exists ? $expense->is_tax_deductible : true))>
        <label for="is_tax_deductible" class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tax deductible</label>
    </div>

    <div class="md:col-span-2 xl:col-span-3">
        <label for="notes" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Internal Notes</label>
        <textarea id="notes" name="notes" rows="4" class="block w-full">{{ old('notes', $expense->notes) }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>
</div>

<div class="mt-6 flex items-center justify-end gap-3">
    <a href="{{ route('expenses.index') }}" class="btn-secondary">Cancel</a>
    <button type="submit" class="btn-primary">{{ $expense->exists ? 'Update Expense' : 'Record Expense' }}</button>
</div>
