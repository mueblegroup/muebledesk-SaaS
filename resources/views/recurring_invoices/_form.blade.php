@props(['recurringInvoice' => null, 'invoice' => null, 'clients'])

@php
    $sourceItems = old('items', $recurringInvoice ? $recurringInvoice->items : ($invoice ? $invoice->items : []));
    $initialItems = collect($sourceItems)->map(fn ($item) => [
        'id' => data_get($item, 'id'),
        'item_name' => data_get($item, 'item_name', ''),
        'description' => data_get($item, 'description', ''),
        'quantity' => data_get($item, 'quantity', 1),
        'price' => data_get($item, 'price', 0),
    ])->values();
    $taxType = old('tax_type', $recurringInvoice?->tax_type ?? $invoice?->tax_type ?? 'none');
    $taxRate = old('tax_rate', $recurringInvoice?->tax_rate ?? $invoice?->tax_rate ?? 0);
    $frequency = old('frequency', $recurringInvoice?->frequency ?? 'monthly');
@endphp

<div x-data="recurringInvoiceForm(
    @js($initialItems),
    @js(old('discount_type', $recurringInvoice?->discount_type ?? $invoice?->discount_type ?? '')),
    {{ (float) old('discount_value', $recurringInvoice?->discount_value ?? $invoice?->discount_value ?? 0) }},
    @js($taxType),
    {{ (float) $taxRate }},
    @js($frequency)
)" x-init="init()" class="space-y-8">
    <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <x-input-label for="client_id" :value="__('Client')" />
            <select id="client_id" name="client_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                <option value="">{{ __('Select a Client') }}</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected(old('client_id', $recurringInvoice?->client_id ?? $invoice?->client_id ?? '') == $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="invoice_prefix" :value="__('Reference Prefix / Label')" />
            <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full" :value="old('invoice_prefix', $recurringInvoice?->invoice_prefix ?? $invoice?->invoice_prefix ?? 'Recurring')" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Actual invoice numbers use the admin sequential numbering settings.</p>
            <x-input-error :messages="$errors->get('invoice_prefix')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="frequency" :value="__('Frequency')" />
            <select id="frequency" name="frequency" x-model="frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'custom' => 'Custom interval'] as $value => $label)
                    <option value="{{ $value }}" @selected($frequency === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('frequency')" class="mt-2" />
        </div>

        <div x-show="frequency === 'custom'" x-cloak class="grid grid-cols-2 gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 dark:border-indigo-900 dark:bg-indigo-950/30">
            <div>
                <x-input-label for="repeat_every" :value="__('Repeat Every')" />
                <x-text-input id="repeat_every" name="repeat_every" type="number" min="1" max="1200" class="mt-1 block w-full" :value="old('repeat_every', $recurringInvoice?->repeat_every ?? 1)" x-bind:required="frequency === 'custom'" />
                <x-input-error :messages="$errors->get('repeat_every')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="repeat_unit" :value="__('Unit')" />
                <select id="repeat_unit" name="repeat_unit" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" x-bind:required="frequency === 'custom'">
                    @foreach(['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('repeat_unit', $recurringInvoice?->repeat_unit ?? 'months') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('repeat_unit')" class="mt-2" />
            </div>
            <p class="col-span-2 text-xs text-indigo-700 dark:text-indigo-300">Example: Repeat every 4 months.</p>
        </div>

        <div>
            <x-input-label for="start_date" :value="__('Start Date')" />
            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', $recurringInvoice?->start_date?->format('Y-m-d') ?? ($invoice?->date?->format('Y-m-d') ?? now()->format('Y-m-d')))" required />
            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="end_date" :value="__('End Date (Optional)')" />
            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date', $recurringInvoice?->end_date?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="is_active" :value="__('Active')" />
            <label class="mt-3 inline-flex items-center gap-3">
                <input type="checkbox" name="is_active" id="is_active" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $recurringInvoice?->is_active ?? true))>
                <span class="text-sm text-gray-600 dark:text-gray-300">Generate invoices when due</span>
            </label>
        </div>
    </section>

    <section>
        <h4 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Recurring Items') }}</h4>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700"><tr><th>Item</th><th>Description</th><th>Qty</th><th>Price</th><th class="text-right">Total</th><th></th></tr></thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                    <template x-for="(item, index) in items" :key="index">
                        <tr>
                            <td class="px-4 py-3"><input type="hidden" x-bind:name="`items[${index}][id]`" x-model="item.id"><x-text-input x-model="item.item_name" x-bind:name="`items[${index}][item_name]`" type="text" class="block w-full" required /></td>
                            <td class="px-4 py-3"><x-text-input x-model="item.description" x-bind:name="`items[${index}][description]`" type="text" class="block w-full" /></td>
                            <td class="px-4 py-3"><x-text-input x-model.number="item.quantity" @input="updateTotals" x-bind:name="`items[${index}][quantity]`" type="number" step="0.01" min="0.01" class="block w-24" required /></td>
                            <td class="px-4 py-3"><x-text-input x-model.number="item.price" @input="updateTotals" x-bind:name="`items[${index}][price]`" type="number" step="0.01" min="0.01" class="block w-28" required /></td>
                            <td class="px-4 py-3 text-right text-sm font-semibold"><span x-text="money(item.quantity * item.price)"></span></td>
                            <td class="px-4 py-3 text-right"><button type="button" @click="removeItem(index)" class="text-sm font-semibold text-red-600">Remove</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <button type="button" @click="addItem" class="mt-4 btn-primary">{{ __('Add Item') }}</button>
        <x-input-error :messages="$errors->get('items')" class="mt-2" />
    </section>

    <section class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        <div><x-input-label for="discount_type" :value="__('Discount Type')" /><select id="discount_type" name="discount_type" x-model="discountType" @change="updateTotals" class="mt-1 block w-full"><option value="">No Discount</option><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select></div>
        <div><x-input-label for="discount_value" :value="__('Discount Value')" /><x-text-input id="discount_value" name="discount_value" x-model.number="discountValue" @input="updateTotals" type="number" step="0.01" min="0" class="mt-1 block w-full" /></div>
        <div><x-input-label for="tax_type" :value="__('Tax Type')" /><select id="tax_type" name="tax_type" x-model="taxType" @change="updateTotals" class="mt-1 block w-full">@foreach(['none' => 'No Tax', 'sst' => 'SST', 'service_tax' => 'Service Tax', 'sales_tax' => 'Sales Tax', 'tourism_tax' => 'Tourism Tax', 'exempt' => 'Tax Exempt', 'zero_rated' => 'Zero Rated', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        <div><x-input-label for="tax_rate" :value="__('Tax Rate (%)')" /><x-text-input id="tax_rate" name="tax_rate" x-model.number="taxRate" @input="updateTotals" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" x-bind:disabled="['none','exempt','zero_rated'].includes(taxType)" /></div>
    </section>

    <section class="rounded-2xl bg-gray-50 p-5 text-right dark:bg-gray-900">
        <p>Sub Total: <strong>RM <span x-text="money(subTotal)"></span></strong></p>
        <p>Discount: <strong>- RM <span x-text="money(discountAmount)"></span></strong></p>
        <p>Tax: <strong>RM <span x-text="money(taxAmount)"></span></strong></p>
        <p class="mt-3 text-2xl font-extrabold text-indigo-700">Grand Total: RM <span x-text="money(finalTotal)"></span></p>
        <input type="hidden" name="sub_total" x-model="subTotal"><input type="hidden" name="discount_amount" x-model="discountAmount"><input type="hidden" name="tax_amount" x-model="taxAmount"><input type="hidden" name="total_amount" x-model="finalTotal">
    </section>

    <div class="flex items-center justify-end gap-3"><a href="{{ route('recurring-invoices.index') }}" class="btn-secondary">Cancel</a><x-primary-button>{{ $recurringInvoice ? __('Update Recurring Invoice') : __('Create Recurring Invoice') }}</x-primary-button></div>
</div>

@push('scripts')
<script>
function recurringInvoiceForm(initialItems = [], initialDiscountType = '', initialDiscountValue = 0, initialTaxType = 'none', initialTaxRate = 0, initialFrequency = 'monthly') {
    return {
        frequency: initialFrequency || 'monthly',
        items: initialItems.length ? initialItems.map(item => ({ id: item.id || null, item_name: item.item_name || '', description: item.description || '', quantity: parseFloat(item.quantity) || 1, price: parseFloat(item.price) || 0 })) : [{ id: null, item_name: '', description: '', quantity: 1, price: 0 }],
        discountType: initialDiscountType || '', discountValue: parseFloat(initialDiscountValue) || 0, taxType: initialTaxType || 'none', taxRate: parseFloat(initialTaxRate) || 0, subTotal: 0, discountAmount: 0, taxAmount: 0, finalTotal: 0,
        init() { this.updateTotals(); },
        addItem() { this.items.push({ id: null, item_name: '', description: '', quantity: 1, price: 0 }); this.updateTotals(); },
        removeItem(index) { if (this.items.length > 1) { this.items.splice(index, 1); this.updateTotals(); } },
        money(value) { return (parseFloat(value) || 0).toFixed(2); },
        updateTotals() {
            this.subTotal = this.items.reduce((sum, item) => sum + ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0)), 0);
            let discount = this.discountType === 'percentage' ? (this.subTotal * this.discountValue) / 100 : (this.discountType === 'fixed' ? this.discountValue : 0);
            this.discountAmount = Math.min(discount || 0, this.subTotal);
            const taxable = Math.max(0, this.subTotal - this.discountAmount);
            if (['none', 'exempt', 'zero_rated'].includes(this.taxType)) this.taxRate = 0;
            this.taxAmount = taxable * (parseFloat(this.taxRate) || 0) / 100;
            this.finalTotal = taxable + this.taxAmount;
        }
    };
}
</script>
@endpush
