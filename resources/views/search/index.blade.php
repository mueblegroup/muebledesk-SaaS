<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Search</h2>
    </x-slot>

    <div class="space-y-6">
        <form method="GET" action="{{ route('search.index') }}" class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label for="q" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Search records</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input id="q" name="q" type="search" value="{{ $query }}" placeholder="Search clients, invoices, quotations, users..." class="block w-full">
                <button type="submit" class="btn-primary">Search</button>
            </div>
        </form>

        @if ($query === '')
            <x-empty-state title="Start searching" message="Use the search box to find records available to your account." />
        @elseif ($results->isEmpty())
            <x-empty-state title="No results found" message="No matching records were found. Try another keyword." />
        @else
            <div class="space-y-3">
                @foreach ($results as $result)
                    <a href="{{ $result['url'] }}" class="block rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ $result['type'] }}</span>
                                <h3 class="mt-3 text-lg font-extrabold text-slate-950 dark:text-white">{{ $result['title'] }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $result['subtitle'] }}</p>
                            </div>
                            <span class="text-slate-400">→</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
