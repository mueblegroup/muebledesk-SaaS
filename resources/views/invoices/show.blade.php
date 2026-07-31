{{-- resources/views/invoices/show.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Invoice Details') }} - #{{ $invoice->invoice_number }}
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

                    <div x-data="{ openPaymentModal: false }" @keydown.escape.window="openPaymentModal = false">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Client: <span class="font-semibold">{{ $invoice->client->name }}</span></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Invoice Date: <span class="font-semibold">{{ $invoice->date->format('Y-m-d') }}</span></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Due Date: <span class="font-semibold">{{ $invoice->due_date->format('Y-m-d') }}</span></p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Status: <span class="font-semibold text-{{ $invoice->status == 'paid' ? 'green' : ($invoice->status == 'overdue' ? 'red' : 'yellow') }}-600">{{ Str::title(str_replace('_', ' ', $invoice->status)) }}</span></p>

                                {{-- Display Sub-Total and Discount --}}
                                <p class="text-sm text-gray-600 dark:text-gray-400">Sub-Total: <span class="font-semibold">${{ number_format($invoice->sub_total, 2) }}</span></p>
                                @if ($invoice->discount_type && $invoice->discount_value > 0)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Discount (
                                        <span class="font-semibold">
                                            @if($invoice->discount_type === 'percentage')
                                                {{ number_format($invoice->discount_value, 2) }}%
                                            @else
                                                Fixed
                                            @endif
                                        </span>)
                                    </p>
                                @else
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Discount: <span class="font-semibold">N/A</span></p>
                                @endif

                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Amount: <span class="font-semibold">${{ number_format($invoice->total_amount, 2) }}</span></p>
                                <p class="text-lg font-bold text-gray-800 dark:text-gray-200">Amount Paid: <span class="text-green-600">${{ number_format($invoice->amount_paid, 2) }}</span></p>
                                <p class="text-xl font-bold text-gray-800 dark:text-gray-200">Amount Due: <span class="text-red-600">${{ number_format($invoice->total_amount - $invoice->amount_paid, 2) }}</span></p>
                            </div>
                            <div class="flex space-x-2">
                                @unless($invoice->isLocked())<a href="{{ route('invoices.edit', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    {{ __('Edit Invoice') }}
                                </a>@else<span class="rounded-xl bg-amber-100 px-3 py-2 text-xs font-bold text-amber-800">🔒 Payment locked</span>@endunless
                                <a href="{{ route('invoices.download', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 mr-2">
                                    Download PDF
                                </a>
                                <a href="{{ route('recurring-invoices.create-from-invoice', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    {{ __('Make Recurring') }}
                                </a>
                                @unless($invoice->isLocked())<form action="{{ route('invoices.destroy', $invoice) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        {{ __('Delete Invoice') }}
                                    </button>
                                </form>@endunless

                                @if ($invoice->total_amount > $invoice->amount_paid)
                                    <button
                                        @click="openPaymentModal = true"
                                        class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        {{ __('Record Payment') }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center px-4 py-2 bg-gray-400 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest">
                                        {{ __('Fully Paid') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mt-8 mb-4">Invoice Items</h4>
                        @if ($invoice->items->isEmpty())
                            <p class="text-gray-600 dark:text-gray-400">No items found for this invoice.</p>
                        @else
                            <div class="overflow-x-auto mb-6">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item Name</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantity</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($invoice->items as $item)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-200">{{ $item->item_name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $item->description ?? 'N/A' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $item->quantity }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">${{ number_format($item->price, 2) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200 text-right">${{ number_format($item->total, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 mt-8 mb-4">Payment History</h4>
                        @if ($invoice->payments->isEmpty())
                            <p class="text-gray-600 dark:text-gray-400">No payments recorded for this invoice yet.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Payment ID</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Method</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Recorded By</th><th>Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($invoice->payments as $payment)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">{{ $payment->id }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">${{ number_format($payment->amount, 2) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $payment->payment_date->format('Y-m-d') }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ Str::title(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $payment->transaction_reference ?? 'N/A' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $payment->recordedBy->name ?? 'System' }}</td><td><a href="{{ route('payments.receipt', $payment) }}" class="font-semibold text-indigo-600">PDF</a></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Record Payment Modal --}}
                        <div x-show="openPaymentModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                <div x-show="openPaymentModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                    x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                    class="fixed inset-0 transition-opacity" aria-hidden="true">
                                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                                </div>

                                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                                <div x-show="openPaymentModal" x-transition:enter="ease-out duration-300"
                                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                    x-transition:leave="ease-in duration-200"
                                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                    class="inline-block align-bottom bg-white dark:bg-gray-900 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                                    role="dialog" aria-modal="true" aria-labelledby="modal-headline">
                                    <div class="bg-white dark:bg-gray-900 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-headline">
                                                    Record Payment for Invoice #{{ $invoice->invoice_number }}
                                                </h3>
                                                <div class="mt-2">
                                                    <form action="{{ route('invoices.payments.store', $invoice) }}" method="POST" class="space-y-4">
                                                        @csrf

                                                        <div>
                                                            <x-input-label for="amount" :value="__('Amount')" />
                                                            <x-text-input id="amount" name="amount" type="number" step="0.01" class="mt-1 block w-full" :value="old('amount', number_format($invoice->total_amount - $invoice->amount_paid, 2, '.', ''))" required autofocus />
                                                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                                        </div>

                                                        <div>
                                                            <x-input-label for="payment_date" :value="__('Payment Date')" />
                                                            <x-text-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full" :value="old('payment_date', now()->format('Y-m-d'))" required />
                                                            <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                                                        </div>

                                                        <div>
                                                            <x-input-label for="payment_method" :value="__('Payment Method')" />
                                                            <select id="payment_method" name="payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                                                                <option value="cash" {{ old('payment_method') == 'cash' ? 'selected' : '' }}>Cash</option>
                                                                <option value="bank_transfer" {{ old('payment_method') == 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                                                                <option value="credit_card" {{ old('payment_method') == 'credit_card' ? 'selected' : '' }}>Credit Card</option>
                                                                <option value="cheque" {{ old('payment_method') == 'cheque' ? 'selected' : '' }}>Cheque</option>
                                                                <option value="online_payment" {{ old('payment_method') == 'online_payment' ? 'selected' : '' }}>Online Payment</option>
                                                            </select>
                                                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                                                        </div>

                                                        <div>
                                                            <x-input-label for="transaction_reference" :value="__('Transaction Reference (Optional)')" />
                                                            <x-text-input id="transaction_reference" name="transaction_reference" type="text" class="mt-1 block w-full" :value="old('transaction_reference')" />
                                                            <x-input-error :messages="$errors->get('transaction_reference')" class="mt-2" />
                                                        </div>

                                                        <div>
                                                            <x-input-label for="notes" :value="__('Notes (Optional)')" />
                                                            <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">{{ old('notes') }}</textarea>
                                                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                                                        </div>

                                                        <div class="flex items-center mt-4">
                                                            <input type="checkbox" id="is_deposit" name="is_deposit" value="1" {{ old('is_deposit') ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600">
                                                            <label for="is_deposit" class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Is this a deposit?') }}</label>
                                                            <x-input-error :messages="$errors->get('is_deposit')" class="mt-2" />
                                                        </div>

                                                        <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                                            <x-primary-button class="ml-3">{{ __('Record Payment') }}</x-primary-button>
                                                            <button type="button" @click="openPaymentModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 dark:border-gray-600">
                                                                {{ __('Cancel') }}
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
