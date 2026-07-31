<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Manage Users') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">All System Users</h3>
                <p class="section-subtitle">Search, filter, export, and manage admin, employee, and customer accounts.</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn-primary">Create New User</a>
        </div>

        <x-filters-toolbar
            :action="route('users.index')"
            search-placeholder="Search name or email..."
            export-route="users.export"
            :filters="[
                ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'placeholder' => 'All roles', 'options' => collect($roles)->mapWithKeys(fn ($role) => [$role->value => ucfirst($role->value)])->toArray()],
                ['name' => 'from', 'label' => 'From', 'type' => 'date'],
                ['name' => 'to', 'label' => 'To', 'type' => 'date'],
                ['name' => 'sort', 'label' => 'Sort', 'type' => 'select', 'placeholder' => 'Default', 'options' => ['name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'created_at' => 'Created Date']],
            ]"
        />

        @if ($users->isEmpty())
            <x-empty-state title="No users found" message="No users matched your current search or filters." :action-url="route('users.create')" action-label="Create User" />
        @else
            <form method="POST" action="{{ route('users.bulk_destroy') }}" x-data="{ selected: [] }" onsubmit="return confirm('Delete selected users?');" class="space-y-4">
                @csrf
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $users->total() }} user(s) found</p>
                    <button type="submit" x-show="selected.length" x-cloak class="rounded-2xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500">Delete Selected</button>
                </div>

                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" @change="selected = $event.target.checked ? Array.from(document.querySelectorAll('.user-checkbox')).map(el => el.value) : []"></th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $listedUser)
                                <tr>
                                    <td><input name="ids[]" value="{{ $listedUser->id }}" type="checkbox" class="user-checkbox" x-model="selected"></td>
                                    <td class="font-semibold text-slate-950 dark:text-white">{{ $listedUser->name }}</td>
                                    <td>{{ $listedUser->email }}</td>
                                    <td>{{ ucfirst($listedUser->role?->value ?? $listedUser->getRawOriginal('role')) }}</td>
                                    <td class="text-right">
                                        @can('update', $listedUser)
                                            <a href="{{ route('users.edit', $listedUser) }}" class="mr-3">Edit</a>
                                        @endcan
                                        @can('delete', $listedUser)
                                            <form action="{{ route('users.destroy', $listedUser) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-500 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>

            <div>{{ $users->links() }}</div>
        @endif
    </div>
</x-app-layout>
