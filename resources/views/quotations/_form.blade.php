@props(['quotation' => null, 'clients'])

@php
    $initialItems = old('items', $quotation ? $quotation->items->map(function($item) {
        return [
            'id' => $item->id,
            'item_name' => $item->item_name,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'price' => $item->price,
            'total' => $item->total,
        ];
    })->values()->all() : [
        ['item_name' => '', 'description' => '', 'quantity' => 1, 'price' => 0.00, 'total' => 0.00]
    ]);

    $selectedClientId = old('client_id', $quotation?->client_id ?? session('quick_client_id'));
    $initialDiscountType = old('discount_type', $quotation->discount_type ?? 'percentage');
    $initialDiscountValue = old('discount_value', $quotation->discount_value ?? 0.00);
    $initialTaxType = old('tax_type', $quotation->tax_type ?? 'none');
    $initialTaxRate = old('tax_rate', $quotation->tax_rate ?? 0.00);
    $taxOptions = [
        'none' => 'No Tax',
        'sst' => 'SST',
        'service_tax' => 'Service Tax',
        'sales_tax' => 'Sales Tax',
        'tourism_tax' => 'Tourism Tax',
        'exempt' => 'Tax Exempt',
        'zero_rated' => 'Zero Rated',
        'other' => 'Other Tax',
    ];
@endphp

<div x-data="{
    items: {{ Js::from($initialItems) }},
    discountType: '{{ $initialDiscountType }}',
    discountValue: {{ (float) $initialDiscountValue }},
    taxType: '{{ $initialTaxType }}',
    taxRate: {{ (float) $initialTaxRate }},
    subTotal: 0,
    discountAmount: 0,
    taxAmount: 0,
    totalAmount: 0,
    calculateItemTotal(index) {
        let item = this.items[index];
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        item.total = parseFloat((quantity * price).toFixed(2));
        this.updateOverallTotals();
    },
    updateOverallTotals() {
        this.subTotal = parseFloat(this.items.reduce((sum, item) => sum + parseFloat(item.total || 0), 0).toFixed(2));
        if (this.discountType === 'percentage') {
            this.discountAmount = (this.subTotal * parseFloat(this.discountValue || 0)) / 100;
        } else if (this.discountType === 'fixed') {
            this.discountAmount = parseFloat(this.discountValue || 0);
        } else {
            this.discountAmount = 0;
        }
        this.discountAmount = Math.min(parseFloat(this.discountAmount.toFixed(2)), this.subTotal);
        const taxableAmount = Math.max(0, this.subTotal - this.discountAmount);
        const taxable = !['none', 'exempt', 'zero_rated'].includes(this.taxType);
        this.taxAmount = taxable ? parseFloat(((taxableAmount * parseFloat(this.taxRate || 0)) / 100).toFixed(2)) : 0;
        if (!taxable) this.taxRate = 0;
        this.totalAmount = parseFloat((taxableAmount + this.taxAmount).toFixed(2));
    },
    addItem() {
        this.items.push({ id: null, item_name: '', description: '', quantity: 1, price: 0.00, total: 0.00 });
        this.updateOverallTotals();
    },
    removeItem(index) {
        if (this.items.length > 1) {
            this.items.splice(index, 1);
            this.updateOverallTotals();
        } else {
            alert('You must have at least one item in the quotation.');
        }
    },
    getErrorMessage(fieldName, index) {
        const errorPath = `items.${index}.${fieldName}`;
        const errors = {{ Js::from($errors->messages()) }};
        return errors[errorPath] ? errors[errorPath][0] : '';
    }
}" x-init="items.forEach((item, index) => calculateItemTotal(index)); updateOverallTotals()">
    @if ($errors->any() && ! $errors->quickClient->any())
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300" role="alert">
            <strong class="font-bold">Whoops!</strong>
            <span class="block sm:inline">There were some problems with your input.</span>
            <ul class="mt-3 list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <x-input-label for="client_id" :value="__('Client')" />
            <select id="client_id" name="client_id" class="mt-1 block w-full" required>
                <option value="">{{ __('Select a Client') }}</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{ (string) $selectedClientId === (string) $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
            @unless($quotation)
                <x-quick-client-form context="quotation" form-id="quick-client-quotation-form" />
            @endunless
        </div>

        @if ($quotation)
            <div>
                <x-input-label for="quote_number_display" :value="__('Quotation Number')" />
                <x-text-input id="quote_number_display" type="text" class="mt-1 block w-full bg-gray-100 dark:bg-gray-700 cursor-not-allowed" :value="$quotation->quote_number" disabled />
            </div>
        @endif

        <div>
            <x-input-label for="date" :value="__('Quotation Date')" />
            <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', $quotation?->date?->format('Y-m-d') ?? now()->format('Y-m-d'))" required />
            <x-input-error :messages="$errors->get('date')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="expiry_date" :value="__('Expiry Date (Optional)')" />
            <x-text-input id="expiry_date" name="expiry_date" type="date" class="mt-1 block w-full" :value="old('expiry_date', $quotation?->expiry_date?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('expiry_date')" class="mt-2" />
        </div>

        @if($quotation)
            <div>
                <x-input-label for="status" :value="__('Status')" />
                <select id="status" name="status" class="mt-1 block w-full" required>
                    @foreach(['draft', 'sent', 'approved', 'rejected', 'converted_to_invoice'] as $statusOption)
                        <option value="{{ $statusOption }}" {{ old('status', $quotation->status) == $statusOption ? 'selected' : '' }}>{{ Str::title(str_replace('_', ' ', $statusOption)) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
        @endif
    </div>

    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mt-8 mb-4">{{ __('Quotation Items') }}</h3>
    <div class="overflow-x-auto mb-6">
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(item, index) in items" :key="index">
                    <tr>
                        <input type="hidden" x-bind:name="`items[${index}][id]`" x-model="item.id">
                        <td>
                            <x-text-input x-model="item.item_name" x-bind:name="`items[${index}][item_name]`" type="text" class="block w-full" placeholder="Item Name" required />
                            <div x-show="getErrorMessage('item_name', index)" class="mt-2 text-sm text-red-600" x-text="getErrorMessage('item_name', index)"></div>
                        </td>
                        <td>
                            <x-rich-description-editor name-expression="`items[${index}][description]`" model="item.description" />
                        </td>
                        <td>
                            <x-text-input x-model.number="item.quantity" @input="calculateItemTotal(index)" x-bind:name="`items[${index}][quantity]`" type="number" min="1" class="block w-24" required />
                        </td>
                        <td>
                            <x-text-input x-model.number="item.price" @input="calculateItemTotal(index)" x-bind:name="`items[${index}][price]`" type="number" step="0.01" min="0.01" class="block w-28" required />
                        </td>
                        <td class="text-right">
                            RM <span x-text="item.total.toFixed(2)"></span>
                            <input type="hidden" x-bind:name="`items[${index}][total]`" x-model.number="item.total">
                        </td>
                        <td class="text-right">
                            <button type="button" @click="removeItem(index)" class="text-red-600 hover:text-red-500">{{ __('Remove') }}</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="flex justify-between items-center mt-4">
            <button type="button" @click="addItem" class="btn-secondary">{{ __('Add Item') }}</button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
        <div>
            <x-input-label for="discount_type" :value="__('Discount Type')" />
            <select id="discount_type" name="discount_type" x-model="discountType" @change="updateOverallTotals()" class="mt-1 block w-full">
                <option value="">{{ __('No Discount') }}</option>
                <option value="percentage">{{ __('Percentage (%)') }}</option>
                <option value="fixed">{{ __('Fixed Amount') }}</option>
            </select>
            <x-input-error :messages="$errors->get('discount_type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="discount_value" :value="__('Discount Value')" />
            <x-text-input id="discount_value" name="discount_value" type="number" step="0.01" min="0" x-model.number="discountValue" @input="updateOverallTotals()" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('discount_value')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="tax_type" :value="__('Tax Type (Optional)')" />
            <select id="tax_type" name="tax_type" x-model="taxType" @change="updateOverallTotals()" class="mt-1 block w-full">
                @foreach($taxOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('tax_type')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="tax_rate" :value="__('Tax Rate (%)')" />
            <x-text-input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" x-model.number="taxRate" @input="updateOverallTotals()" x-bind:disabled="['none', 'exempt', 'zero_rated'].includes(taxType)" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('tax_rate')" class="mt-2" />
        </div>
    </div>

    <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6 space-y-2">
        <div class="flex justify-between"><span class="font-medium">{{ __('Sub-Total:') }}</span><span>RM <span x-text="subTotal.toFixed(2)"></span></span><input type="hidden" name="sub_total" x-model="subTotal"></div>
        <div class="flex justify-between"><span class="font-medium">{{ __('Discount:') }}</span><span>RM <span x-text="discountAmount.toFixed(2)"></span></span></div>
        <div class="flex justify-between"><span class="font-medium">{{ __('Tax:') }}</span><span>RM <span x-text="taxAmount.toFixed(2)"></span></span><input type="hidden" name="tax_amount" x-model="taxAmount"></div>
        <div class="flex justify-between text-xl font-bold border-t border-gray-300 dark:border-gray-600 pt-2 mt-2"><span>{{ __('TOTAL AMOUNT:') }}</span><span>RM <span x-text="totalAmount.toFixed(2)"></span></span><input type="hidden" name="total_amount" x-model="totalAmount"></div>
    </div>

    <div class="flex items-center justify-end mt-6">
        <x-primary-button class="ml-4">{{ $quotation ? __('Update Quotation') : __('Create Quotation') }}</x-primary-button>
    </div>
</div>
