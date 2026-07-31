<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('API Keys') }}
        </h2>
    </x-slot>

    <div class="space-y-8">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            API keys provide programmatic access to Mueble Desk. Store keys securely. Full keys are shown only once during creation.
        </div>

        @if (session('plain_api_key'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950">
                <p class="text-sm font-bold text-emerald-800 dark:text-emerald-200">Copy this API key now. It will not be shown again.</p>
                <pre class="mt-3 overflow-x-auto rounded-xl bg-white p-3 text-sm font-mono text-slate-900 dark:bg-slate-900 dark:text-slate-100">{{ session('plain_api_key') }}</pre>
            </div>
        @endif

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                <p class="font-bold">Please fix the following:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-extrabold text-slate-950 dark:text-white">Create API Key</h3>
            <form method="POST" action="{{ route('admin.api-keys.store') }}" class="mt-5 space-y-5">
                @csrf
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Name</label>
                        <input name="name" value="{{ old('name') }}" class="block w-full" required placeholder="Zapier / n8n / Mobile App">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Owner User</label>
                        <select name="user_id" class="block w-full">
                            <option value="">System / No user owner</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Allowed IPs</label>
                        <input name="allowed_ips" value="{{ old('allowed_ips') }}" class="block w-full" placeholder="Optional, comma-separated IPs">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Expires At</label>
                        <input name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="block w-full">
                    </div>
                </div>

                <div>
                    <p class="mb-3 text-sm font-bold text-slate-700 dark:text-slate-200">Permissions</p>
                    <label class="mb-3 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold dark:border-slate-800">
                        <input type="checkbox" name="permissions[]" value="*" @checked(in_array('*', old('permissions', []), true))>
                        Full access (*)
                    </label>
                    <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($permissions as $permission)
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, old('permissions', []), true))>
                                <span class="font-mono">{{ $permission }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button class="btn-primary" type="submit">Create API Key</button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-6 dark:border-slate-800">
                <h3 class="text-lg font-extrabold text-slate-950 dark:text-white">Existing Keys</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Prefix</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Owner</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Permissions</th>
                            <th class="px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-bold uppercase text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse ($apiKeys as $apiKey)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $apiKey->name }}</td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $apiKey->key_prefix }}</td>
                                <td class="px-4 py-3 text-sm">{{ $apiKey->user?->name ?? 'System' }}</td>
                                <td class="px-4 py-3 text-xs font-mono">{{ implode(', ', array_slice($apiKey->permissions ?? [], 0, 6)) }}{{ count($apiKey->permissions ?? []) > 6 ? '…' : '' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($apiKey->revoked_at)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-bold text-red-700">Revoked</span>
                                    @elseif ($apiKey->expires_at && $apiKey->expires_at->isPast())
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-700">Expired</span>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-700">Active</span>
                                    @endif
                                    <div class="mt-1 text-xs text-slate-500">Last used: {{ $apiKey->last_used_at?->diffForHumans() ?? 'Never' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @unless ($apiKey->revoked_at)
                                        <form method="POST" action="{{ route('admin.api-keys.revoke', $apiKey) }}" class="inline" onsubmit="return confirm('Revoke this API key?')">
                                            @csrf
                                            @method('PATCH')
                                            <button class="text-sm font-bold text-amber-600 hover:text-amber-700">Revoke</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.api-keys.destroy', $apiKey) }}" class="ml-3 inline" onsubmit="return confirm('Delete this API key permanently?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-sm font-bold text-red-600 hover:text-red-700">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No API keys created yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $apiKeys->links() }}</div>
        </section>
    </div>
</x-app-layout>
