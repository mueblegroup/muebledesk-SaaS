<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Edit Expense</h2>
    </x-slot>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-6">
            <h3 class="section-title">{{ $expense->expense_number }}</h3>
            <p class="section-subtitle">Update company spend details.</p>
        </div>
        <form method="POST" action="{{ route('expenses.update', $expense) }}">
            @method('PUT')
            @include('expenses._form')
        </form>
    </section>
</x-app-layout>
