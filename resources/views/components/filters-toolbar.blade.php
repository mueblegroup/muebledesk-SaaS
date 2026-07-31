@props([
    'action' => url()->current(),
    'searchPlaceholder' => 'Search...',
    'exportRoute' => null,
    'filters' => [],
])

<div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <form method="GET" action="{{ $action }}" class="grid gap-3 lg:grid-cols-12 lg:items-end">
        <div class="lg:col-span-4">
            <label for="q" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Search</label>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ $searchPlaceholder }}" class="block w-full">
        </div>

        @foreach ($filters as $filter)
            <div class="lg:col-span-2">
                <label for="{{ $filter['name'] }}" class="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $filter['label'] }}</label>
                @if (($filter['type'] ?? 'select') === 'select')
                    <select id="{{ $filter['name'] }}" name="{{ $filter['name'] }}" class="block w-full">
                        <option value="">{{ $filter['placeholder'] ?? 'All' }}</option>
                        @foreach (($filter['options'] ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected((string) request($filter['name']) === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <input id="{{ $filter['name'] }}" name="{{ $filter['name'] }}" type="{{ $filter['type'] }}" value="{{ request($filter['name']) }}" class="block w-full">
                @endif
            </div>
        @endforeach

        <div class="flex flex-wrap gap-2 lg:col-span-{{ max(2, 8 - (count($filters) * 2)) }}">
            <button type="submit" class="btn-primary px-4 py-2.5">Apply</button>
            <a href="{{ $action }}" class="btn-secondary px-4 py-2.5">Reset</a>
            @if ($exportRoute)
                <a href="{{ route($exportRoute, request()->query()) }}" class="btn-secondary px-4 py-2.5">Export CSV</a>
            @endif
        </div>
    </form>
</div>
