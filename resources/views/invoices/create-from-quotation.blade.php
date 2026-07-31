<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Create Invoice from Quotation') }} #{{ $quotation->quote_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if (session('success'))
                        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded relative dark:bg-red-900 dark:text-red-100 dark:border-red-700" role="alert">
                            <strong class="font-bold">Whoops!</strong>
                            <span class="block sm:inline">There were some problems with your input.</span>
                            <ul class="mt-3 list-disc list-inside text-sm">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('invoices.store') }}">
                        @csrf

                        {{-- Hidden input to link to the original quotation --}}
                        <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Client</label>
                                <select id="client_id" name="client_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required readonly>
                                    <option value="{{ $quotation->client->id }}" selected>
                                        {{ $quotation->client->name }}
                                    </option>
                                </select>
                                {{-- Ensure client_id is submitted even if readonly --}}
                                <input type="hidden" name="client_id" value="{{ $quotation->client->id }}">
                                @error('client_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Invoice Date</label>
                                <input type="date" name="date" id="date" value="{{ old('date', date('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                                @error('date')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Due Date</label>
                                <input type="date" name="due_date" id="due_date" value="{{ old('due_date', \Carbon\Carbon::now()->addDays(30)->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                                @error('due_date')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Display Quotation Number (read-only for clarity) --}}
                            <div>
                                <label for="quotation_number_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quotation Number</label>
                                <input type="text" id="quotation_number_display" value="{{ $quotation->quote_number }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 cursor-not-allowed" disabled>
                            </div>

                            {{-- Hidden fields for discount details from quotation --}}
                            <input type="hidden" name="discount_type" value="{{ $quotation->discount_type }}">
                            <input type="hidden" name="discount_value" value="{{ $quotation->discount_value }}">
                            <input type="hidden" name="sub_total" value="{{ $quotation->sub_total }}">
                            <input type="hidden" name="total_amount" value="{{ $quotation->total_amount }}">
                            {{-- total_amount will be derived from sub_total and discount in Invoice model --}}
                        </div>

                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mt-8 mb-4">Invoice Items</h3>
                        <div id="invoice-items-container">
                            @forelse(old('items', $quotation->items) as $key => $item)
                                <div class="invoice-item grid grid-cols-1 md:grid-cols-6 gap-4 border border-gray-200 dark:border-gray-700 p-4 rounded-md mb-4 relative">
                                    {{-- No 'id' for items here, as they will be new InvoiceItems --}}
                                    <div>
                                        <label for="item_name_{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                                        <input type="text" name="items[{{ $key }}][item_name]" id="item_name_{{ $key }}" value="{{ old('items.' . $key . '.item_name', $item->item_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                                        @error('items.' . $key . '.item_name')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="description_{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description (Optional)</label>
                                        <textarea name="items[{{ $key }}][description]" id="description_{{ $key }}" rows="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">{{ old('items.' . $key . '.description', $item->description ?? '') }}</textarea>
                                        @error('items.' . $key . '.description')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="quantity_{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                        <input type="number" name="items[{{ $key }}][quantity]" id="quantity_{{ $key }}" value="{{ old('items.' . $key . '.quantity', $item->quantity ?? '') }}" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 item-quantity" required>
                                        @error('items.' . $key . '.quantity')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="price_{{ $key }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                                        <input type="number" step="0.01" name="items[{{ $key }}][price]" id="price_{{ $key }}" value="{{ old('items.' . $key . '.price', $item->price ?? '') }}" min="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 item-price" required>
                                        @error('items.' . $key . '.price')
                                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total</label>
                                        <p class="mt-1 px-3 py-2 bg-gray-100 dark:bg-gray-700 dark:text-gray-200 rounded-md total-item-price">{{ number_format(($item->quantity ?? 0) * ($item->price ?? 0), 2) }}</p>
                                        {{-- Hidden input for item total to pass to controller if needed for direct saving --}}
                                        <input type="hidden" name="items[{{ $key }}][total]" value="{{ ($item->quantity ?? 0) * ($item->price ?? 0) }}" class="hidden-item-total">
                                    </div>
                                    <button type="button" class="remove-item-btn absolute top-2 right-2 text-red-500 hover:text-red-700">X</button>
                                </div>
                            @empty
                                {{-- Fallback if quotation has no items, or old('items') is empty --}}
                                <div class="invoice-item grid grid-cols-1 md:grid-cols-6 gap-4 border border-gray-200 dark:border-gray-700 p-4 rounded-md mb-4 relative">
                                    <div>
                                        <label for="item_name_0" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                                        <input type="text" name="items[0][item_name]" id="item_name_0" value="{{ old('items.0.item_name', '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="description_0" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description (Optional)</label>
                                        <textarea name="items[0][description]" id="description_0" rows="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">{{ old('items.0.description', '') }}</textarea>
                                    </div>
                                    <div>
                                        <label for="quantity_0" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                                        <input type="number" name="items[0][quantity]" id="quantity_0" value="{{ old('items.0.quantity', 1) }}" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 item-quantity" required>
                                    </div>
                                    <div>
                                        <label for="price_0" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                                        <input type="number" step="0.01" name="items[0][price]" id="price_0" value="{{ old('items.0.price', 0.01) }}" min="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 item-price" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total</label>
                                        <p class="mt-1 px-3 py-2 bg-gray-100 dark:bg-gray-700 dark:text-gray-200 rounded-md total-item-price">0.00</p>
                                        <input type="hidden" name="items[0][total]" value="0.00" class="hidden-item-total">
                                    </div>
                                    <button type="button" class="remove-item-btn absolute top-2 right-2 text-red-500 hover:text-red-700">X</button>
                                </div>
                            @endforelse
                        </div>

                        <button type="button" id="add-item-btn" class="mt-4 inline-flex items-center px-4 py-2 bg-gray-200 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            Add Item
                        </button>

                        {{-- Discount and Total Display --}}
                        <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Sub-Total:') }}</span>
                                <span class="text-lg font-medium text-gray-900 dark:text-gray-100">RM <span id="sub-total-display">{{ number_format($quotation->sub_total ?? 0, 2) }}</span></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    {{ __('Discount') }}
                                    @if($quotation->discount_value > 0)
                                        @if($quotation->discount_type === 'percentage')
                                            ({{ number_format($quotation->discount_value, 2) }}%):
                                        @else
                                            (Fixed):
                                        @endif
                                    @else
                                        :
                                    @endif
                                </span>
                                <span class="text-lg font-medium text-gray-900 dark:text-gray-100">- RM <span id="discount-amount-display">{{ number_format($quotation->discount_amount ?? 0, 2) }}</span></span>
                            </div>
                            <div class="flex justify-between items-center text-xl font-bold border-t border-gray-300 dark:border-gray-600 pt-2 mt-2">
                                <span>{{ __('TOTAL AMOUNT:') }}</span>
                                <span>RM <span id="grand-total-display">{{ number_format($quotation->total_amount ?? 0, 2) }}</span></span>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Create Invoice
                            </button>
                            <a href="{{ route('quotations.show', $quotation->id) }}" class="ml-4 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest bg-white shadow-sm hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:ring focus:ring-blue-200 disabled:opacity-25 transition ease-in-out duration-150 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:text-gray-400">
                                Cancel
                            </a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let itemIndex = {{ old('items') ? count(old('items')) : ($quotation->items->count() > 0 ? $quotation->items->count() : 0) }}; // Start at 0 for new item if no existing ones
            const invoiceItemsContainer = document.getElementById('invoice-items-container');
            const addItemBtn = document.getElementById('add-item-btn');

            // Get initial discount values from hidden inputs
            const discountType = document.querySelector('input[name="discount_type"]').value;
            const discountValue = parseFloat(document.querySelector('input[name="discount_value"]').value) || 0;

            function calculateAndDisplayTotals() {
                let currentSubTotal = 0;
                document.querySelectorAll('.invoice-item').forEach(itemDiv => {
                    const quantityInput = itemDiv.querySelector('.item-quantity');
                    const priceInput = itemDiv.querySelector('.item-price');
                    const totalItemPriceDisplay = itemDiv.querySelector('.total-item-price');
                    const hiddenItemTotalInput = itemDiv.querySelector('.hidden-item-total');

                    const quantity = parseFloat(quantityInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;
                    const itemTotal = quantity * price;

                    totalItemPriceDisplay.textContent = itemTotal.toFixed(2);
                    hiddenItemTotalInput.value = itemTotal.toFixed(2); // Update hidden input for submission
                    currentSubTotal += itemTotal;
                });

                document.getElementById('sub-total-display').textContent = currentSubTotal.toFixed(2);

                let currentDiscountAmount = 0;
                if (discountType === 'percentage') {
                    currentDiscountAmount = (currentSubTotal * discountValue) / 100;
                } else if (discountType === 'fixed') {
                    currentDiscountAmount = discountValue;
                }
                currentDiscountAmount = Math.min(currentDiscountAmount, currentSubTotal); // Discount cannot exceed sub-total

                document.getElementById('discount-amount-display').textContent = currentDiscountAmount.toFixed(2);

                let currentGrandTotal = Math.max(0, currentSubTotal - currentDiscountAmount);
                document.getElementById('grand-total-display').textContent = currentGrandTotal.toFixed(2);

                // Update hidden total amount input before submission
                document.querySelector('input[name="sub_total"]').value = currentSubTotal.toFixed(2);
                document.querySelector('input[name="total_amount"]').value = currentGrandTotal.toFixed(2);
            }

            function setupItemListeners(itemDiv) {
                const quantityInput = itemDiv.querySelector('.item-quantity');
                const priceInput = itemDiv.querySelector('.item-price');
                const removeItemBtn = itemDiv.querySelector('.remove-item-btn');

                quantityInput.addEventListener('input', calculateAndDisplayTotals);
                priceInput.addEventListener('input', calculateAndDisplayTotals);
                removeItemBtn.addEventListener('click', function() {
                    itemDiv.remove();
                    calculateAndDisplayTotals();
                });
            }

            // Add event listeners to initial items (pre-filled from quotation)
            invoiceItemsContainer.querySelectorAll('.invoice-item').forEach(setupItemListeners);

            addItemBtn.addEventListener('click', function () {
                const newItemHtml = `
                    <div class="invoice-item grid grid-cols-1 md:grid-cols-6 gap-4 border border-gray-200 dark:border-gray-700 p-4 rounded-md mb-4 relative">
                        <div>
                            <label for="item_name_${itemIndex}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Item Name</label>
                            <input type="text" name="items[${itemIndex}][item_name]" id="item_name_${itemIndex}" value="" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200" required>
                        </div>
                        <div class="md:col-span-2">
                            <label for="description_${itemIndex}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description (Optional)</label>
                            <textarea name="items[${itemIndex}][description]" id="description_${itemIndex}" rows="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200"></textarea>
                        </div>
                        <div>
                            <label for="quantity_${itemIndex}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label>
                            <input type="number" name="items[${itemIndex}][quantity]" id="quantity_${itemIndex}" value="1" min="1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 item-quantity" required>
                        </div>
                        <div>
                            <label for="price_${itemIndex}" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price</label>
                            <input type="number" step="0.01" name="items[${itemIndex}][price]" id="price_${itemIndex}" value="0.01" min="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 item-price" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total</label>
                            <p class="mt-1 px-3 py-2 bg-gray-100 dark:bg-gray-700 dark:text-gray-200 rounded-md total-item-price">0.00</p>
                            <input type="hidden" name="items[${itemIndex}][total]" value="0.00" class="hidden-item-total">
                        </div>
                        <button type="button" class="remove-item-btn absolute top-2 right-2 text-red-500 hover:text-red-700">X</button>
                    </div>
                `;
                const newItemDiv = document.createElement('div');
                newItemDiv.innerHTML = newItemHtml.trim();
                const actualItemDiv = newItemDiv.firstElementChild;
                invoiceItemsContainer.appendChild(actualItemDiv);

                setupItemListeners(actualItemDiv); // Setup listeners for the new item

                itemIndex++;
                calculateAndDisplayTotals(); // Recalculate totals after adding an item
            });

            // Initial calculation on page load
            calculateAndDisplayTotals();
        });
    </script>
    @endpush
</x-app-layout>