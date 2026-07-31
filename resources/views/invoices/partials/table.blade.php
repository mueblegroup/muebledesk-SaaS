<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sub-Total</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
            @forelse($invoices as $invoice)
            <tr>
                <td class="px-6 py-4">{{ $invoice->invoice_number ?? 'N/A' }}</td>
                <td class="px-6 py-4">{{ $invoice->client->name ?? 'Unknown Client' }}</td>
                <td class="px-6 py-4">${{ number_format($invoice->sub_total ?? 0, 2) }}</td>
                <td class="px-6 py-4">
                    @if ($invoice->discount_type && $invoice->discount_value > 0)
                        {{ $invoice->discount_type === 'percentage' 
                            ? number_format($invoice->discount_value, 2) . '%' 
                            : '$' . number_format($invoice->discount_value, 2) }}
                    @else
                        No Discount
                    @endif
                </td>
                <td class="px-6 py-4">${{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                <td class="px-6 py-4">{{ Str::title(str_replace('_', ' ', $invoice->status)) ?? 'N/A' }}</td>
                <td class="px-6 py-4">{{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') : 'N/A' }}</td>
                <td class="px-6 py-4 text-right">
                    <a href="{{ route('invoices.show', $invoice) }}" class="text-indigo-600 hover:text-indigo-900 mr-2">View</a>
                    <a href="{{ route('invoices.edit', $invoice) }}" class="text-blue-600 hover:text-blue-900 mr-2">Edit</a>
                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline-block" onsubmit="return confirm('Delete this invoice?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-6 py-4 text-center text-gray-500">No invoices found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
