<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Record Expense</h2>
    </x-slot>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-6">
            <h3 class="section-title">Company Spend</h3>
            <p class="section-subtitle">Record expenses for P&L reporting and year-end financial summaries.</p>
        </div>
        <form method="POST" action="{{ route('expenses.store') }}">
            @include('expenses._form')
        </form>
    </section>
</x-app-layout>
