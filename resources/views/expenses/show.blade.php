<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Expense Details</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="section-title">{{ $expense->expense_number }}</h3>
                <p class="section-subtitle">Recorded by {{ $expense->recordedBy->name ?? 'N/A' }} on {{ optional($expense->created_at)->format('Y-m-d H:i') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('expenses.edit', $expense) }}" class="btn-secondary">Edit</a>
                <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger" type="submit">Delete</button>
                </form>
            </div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    'Date' => optional($expense->expense_date)->format('Y-m-d'),
                    'Category' => str($expense->category)->replace('_', ' ')->title(),
                    'Vendor' => $expense->vendor ?: '—',
                    'Amount' => $expense->currency.' '.number_format((float) $expense->amount, 2),
                    'Payment Method' => $expense->payment_method ? str($expense->payment_method)->replace('_', ' ')->title() : '—',
                    'Reference' => $expense->reference_number ?: '—',
                    'Billable' => $expense->is_billable ? 'Yes' : 'No',
                    'Tax Deductible' => $expense->is_tax_deductible ? 'Yes' : 'No',
                ] as $label => $value)
                    <div>
                        <dt class="text-xs font-bold uppercase text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-950 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="md:col-span-2 xl:col-span-3">
                    <dt class="text-xs font-bold uppercase text-slate-500">Description</dt>
                    <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $expense->description }}</dd>
                </div>
                @if ($expense->notes)
                    <div class="md:col-span-2 xl:col-span-3">
                        <dt class="text-xs font-bold uppercase text-slate-500">Notes</dt>
                        <dd class="mt-1 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $expense->notes }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>
</x-app-layout>
