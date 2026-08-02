<x-superadmin-layout>
    <x-slot name="title">Companies</x-slot>
    <x-slot name="header">
        <div><h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">Companies</h1><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage every client company from the platform control plane.</p></div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($counts as $label => $count)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ str($label)->replace('_', ' ')->title() }}</p>
                    <p class="mt-2 text-3xl font-black">{{ $count }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,1fr)_220px_auto] dark:border-slate-800 dark:bg-slate-900">
            <input name="search" value="{{ request('search') }}" placeholder="Search company, slug, email or registration">
            <select name="status"><option value="">All statuses</option><option value="active" @selected(request('status')==='active')>Active subscription</option><option value="inactive" @selected(request('status')==='inactive')>Inactive / no plan</option></select>
            <button class="btn-secondary" type="submit">Filter</button>
        </form>

        <div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table>
                <thead><tr><th>Company</th><th>Owner</th><th>Plan</th><th>Seats</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($companies as $company)
                        @php($active = in_array($company->subscription?->status, ['active','trialing'], true))
                        <tr>
                            <td><div class="font-extrabold">{{ $company->name }}</div><div class="text-xs text-slate-500">{{ $company->slug }}</div></td>
                            <td>{{ $company->owners->first()?->email ?? '—' }}</td>
                            <td>{{ $company->subscription?->plan?->name ?? 'No plan' }}</td>
                            <td>{{ $company->users_count }} / {{ $company->subscription?->seats ?? '—' }}</td>
                            <td><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $company->subscription?->status ?? 'unsubscribed' }}</span></td>
                            <td class="text-right"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('superadmin.companies.show', $company) }}">Manage</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-slate-500">No companies found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $companies->links() }}
    </div>
</x-superadmin-layout>
