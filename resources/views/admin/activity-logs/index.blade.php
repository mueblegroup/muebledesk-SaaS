<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Activity Logs</h2></x-slot>
    <div class="space-y-6">
        <div><h3 class="section-title">Audit History</h3><p class="section-subtitle">Immutable operational history showing who changed what and when.</p></div>
        <form method="GET" class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 md:grid-cols-3 dark:border-slate-800 dark:bg-slate-900">
            <input name="q" value="{{ request('q') }}" placeholder="Search descriptions..." class="block w-full">
            <input name="event" value="{{ request('event') }}" placeholder="Event, e.g. invoice.updated" class="block w-full">
            <button class="btn-primary">Filter Logs</button>
        </form>
        <div class="overflow-x-auto"><table><thead><tr><th>Time</th><th>Actor</th><th>Event</th><th>Description</th><th>IP Address</th><th>Changes</th></tr></thead><tbody>
            @forelse($logs as $log)<tr><td class="whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td><td>{{ $log->actor?->name ?? 'System' }}</td><td><span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ $log->event }}</span></td><td>{{ $log->description }}</td><td>{{ $log->ip_address ?? '—' }}</td><td><details><summary class="cursor-pointer text-indigo-600">View</summary><pre class="mt-2 max-w-xl whitespace-pre-wrap text-xs">{{ json_encode(['before' => $log->old_values, 'after' => $log->new_values], JSON_PRETTY_PRINT) }}</pre></details></td></tr>
            @empty<tr><td colspan="6" class="text-center text-slate-500">No activity has been recorded.</td></tr>@endforelse
        </tbody></table></div>{{ $logs->links() }}
    </div>
</x-app-layout>
