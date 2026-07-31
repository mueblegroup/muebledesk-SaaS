<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">{{ __('Create New Invoice') }}</h2>
    </x-slot>

    @php
        $initialItems = old('items', [['item_name' => '', 'description' => '', 'quantity' => 1, 'price' => 0.01, 'total' => 0.01]]);
        $selectedClientId = old('client_id', session('quick_client_id'));
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

    <div class="space-y-6" x-data="{
        items: {{ Js::from($initialItems) }},
        discountType: '{{ old('discount_type', '') }}',
        discountValue: {{ (float) old('discount_value', 0) }},
        taxType: '{{ old('tax_type', 'none') }}',
        taxRate: {{ (float) old('tax_rate', 0) }},
        subTotal: 0,
        discountAmount: 0,
        taxAmount: 0,
        totalAmount: 0,
        calculateItem(index) {
            const quantity = parseFloat(this.items[index].quantity) || 0;
            const price = parseFloat(this.items[index].price) || 0;
            this.items[index].total = parseFloat((quantity * price).toFixed(2));
            this.calculateTotals();
        },
        calculateTotals() {
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
            this.items.push({ item_name: '', description: '', quantity: 1, price: 0.01, total: 0.01 });
            this.calculateTotals();
        },
        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
                this.calculateTotals();
            }
        }
    }" x-init="items.forEach((item, index) => calculateItem(index)); calculateTotals()">
        <div>
            <h3 class="section-title">Invoice Details</h3>
            <p class="section-subtitle">Create invoices with optional tax support for future e-invoice mapping.</p>
        </div>

        @if ($errors->any() && ! $errors->quickClient->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                <strong class="font-bold">Please fix the following:</strong>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="quick-client-invoice-form" method="POST" action="{{ route('clients.quick_store') }}">
            @csrf
        </form>

        <form action="{{ route('invoices.store') }}" method="POST" class="space-y-8">
            @csrf

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div class="xl:col-span-2">
                    <div class="flex items-end justify-between gap-3">
                        <div class="flex-1">
                            <x-input-label for="client_id" :value="__('Client')" />
                            <select id="client_id" name="client_id" class="mt-1 block w-full" required>
                                <option value="">Select a Client</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ (string) $selectedClientId === (string) $client->id ? 'selected' : '' }}>{{ $client->name }} ({{ $client->email }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
                    <x-quick-client-form context="invoice" form-id="quick-client-invoice-form" />
                </div>

                <div>
                    <x-input-label for="date" :value="__('Issue Date')" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" :value="old('date', now()->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="due_date" :value="__('Due Date')" />
                    <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" :value="old('due_date', now()->addDays(30)->format('Y-m-d'))" required />
                    <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                </div>
            </div>

            <div>
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-950 dark:text-white">Invoice Items</h3>
                    <button type="button" @click="addItem" class="btn-secondary">Add Item</button>
                </div>

                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Description</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr>
                                    <td><input type="text" x-model="item.item_name" x-bind:name="`items[${index}][item_name]`" class="block w-full" required></td>
                                    <td><x-rich-description-editor name-expression="`items[${index}][description]`" model="item.description" /></td>
                                    <td><input type="number" min="1" x-model.number="item.quantity" @input="calculateItem(index)" x-bind:name="`items[${index}][quantity]`" class="block w-24" required></td>
                                    <td><input type="number" min="0.01" step="0.01" x-model.number="item.price" @input="calculateItem(index)" x-bind:name="`items[${index}][price]`" class="block w-28" required></td>
                                    <td class="text-right">RM <span x-text="item.total.toFixed(2)"></span><input type="hidden" x-bind:name="`items[${index}][total]`" x-model="item.total"></td>
                                    <td class="text-right"><button type="button" @click="removeItem(index)" class="text-red-600 hover:text-red-500">Remove</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <x-input-label for="discount_type" :value="__('Discount Type')" />
                    <select id="discount_type" name="discount_type" x-model="discountType" @change="calculateTotals()" class="mt-1 block w-full">
                        <option value="">No Discount</option>
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>

                <div>
                    <x-input-label for="discount_value" :value="__('Discount Value')" />
                    <x-text-input id="discount_value" name="discount_value" type="number" step="0.01" min="0" x-model.number="discountValue" @input="calculateTotals()" class="mt-1 block w-full" />
                </div>

                <div>
                    <x-input-label for="tax_type" :value="__('Tax Type (Optional)')" />
                    <select id="tax_type" name="tax_type" x-model="taxType" @change="calculateTotals()" class="mt-1 block w-full">
                        @foreach($taxOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="tax_rate" :value="__('Tax Rate (%)')" />
                    <x-text-input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" x-model.number="taxRate" @input="calculateTotals()" x-bind:disabled="['none', 'exempt', 'zero_rated'].includes(taxType)" class="mt-1 block w-full" />
                </div>
            </div>

            <div class="ml-auto max-w-xl space-y-3 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex justify-between"><span>Sub Total</span><strong>RM <span x-text="subTotal.toFixed(2)"></span></strong><input type="hidden" name="sub_total" x-model="subTotal"></div>
                <div class="flex justify-between"><span>Discount</span><strong>RM <span x-text="discountAmount.toFixed(2)"></span></strong><input type="hidden" name="discount_amount" x-model="discountAmount"></div>
                <div class="flex justify-between"><span>Tax</span><strong>RM <span x-text="taxAmount.toFixed(2)"></span></strong><input type="hidden" name="tax_amount" x-model="taxAmount"></div>
                <div class="flex justify-between border-t border-slate-200 pt-3 text-xl dark:border-slate-800"><span class="font-black">Grand Total</span><strong>RM <span x-text="totalAmount.toFixed(2)"></span></strong><input type="hidden" name="total_amount" x-model="totalAmount"></div>
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('Create Invoice') }}</x-primary-button>
                <a href="{{ route('invoices.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
