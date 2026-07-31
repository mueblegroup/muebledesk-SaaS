<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Manage Clients') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">All Clients</h3>
                <p class="section-subtitle">Search, filter, export, and manage client profiles.</p>
            </div>
            <a href="{{ route('clients.create') }}" class="btn-primary">Create New Client</a>
        </div>

        <x-filters-toolbar
            :action="route('clients.index')"
            search-placeholder="Search name, email, phone, TIN..."
            export-route="clients.export"
            :filters="[
                ['name' => 'from', 'label' => 'From', 'type' => 'date'],
                ['name' => 'to', 'label' => 'To', 'type' => 'date'],
                ['name' => 'sort', 'label' => 'Sort', 'type' => 'select', 'placeholder' => 'Default', 'options' => ['created_at' => 'Created Date', 'name' => 'Name', 'email' => 'Email']],
                ['name' => 'direction', 'label' => 'Direction', 'type' => 'select', 'placeholder' => 'Default', 'options' => ['asc' => 'Ascending', 'desc' => 'Descending']],
            ]"
        />

        @if ($clients->isEmpty())
            <x-empty-state title="No clients found" message="No client records matched your current search or filters." :action-url="route('clients.create')" action-label="Create Client" />
        @else
            <form method="POST" action="{{ route('clients.bulk_destroy') }}" x-data="{ selected: [] }" onsubmit="return confirm('Delete selected clients?');" class="space-y-4">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $clients->total() }} client(s) found</p>
                    <button type="submit" x-show="selected.length" x-cloak class="rounded-2xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500">Delete Selected</button>
                </div>

                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('.client-checkbox')).map(el => el.value) : []"></th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Phone</th>
                                <th scope="col">TIN Number</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($clients as $client)
                                <tr>
                                    <td><input name="ids[]" value="{{ $client->id }}" type="checkbox" class="client-checkbox" x-model="selected"></td>
                                    <td class="font-semibold text-slate-950 dark:text-white">{{ $client->name ?? 'N/A' }}</td>
                                    <td>{{ $client->email ?? 'N/A' }}</td>
                                    <td>{{ $client->phone ?? 'N/A' }}</td>
                                    <td>{{ $client->tin_number ?? 'N/A' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('clients.show', $client) }}" class="mr-3">View</a>
                                        <a href="{{ route('clients.edit', $client) }}" class="mr-3">Edit</a>
                                        <form action="{{ route('clients.destroy', $client) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this client?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <div>{{ $clients->links() }}</div>
        @endif
    </div>
</x-app-layout>
