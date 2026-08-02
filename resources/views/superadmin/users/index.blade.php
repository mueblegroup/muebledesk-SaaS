<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">Superadmin accounts</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create and manage platform-level administrators.</p>
        </div>
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
        <form method="POST" action="{{ route('superadmin.users.store') }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <div>
                <h2 class="text-lg font-extrabold text-slate-950 dark:text-white">Add superadmin</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This account will have full platform access.</p>
            </div>

            <div>
                <x-input-label for="name" value="Full name" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="Email address" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="phone" value="Phone number" />
                <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone')" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="Temporary password" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="Confirm password" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            </div>

            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500">
                Create superadmin
            </button>
        </form>

        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800">
                <h2 class="text-lg font-extrabold text-slate-950 dark:text-white">Platform administrators</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $superadmins->count() }} active superadmin account{{ $superadmins->count() === 1 ? '' : 's' }}.</p>
            </div>

            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($superadmins as $superadmin)
                    <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-sm font-black text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ strtoupper(substr($superadmin->name, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-extrabold text-slate-950 dark:text-white">{{ $superadmin->name }}</p>
                                    @if (auth()->id() === $superadmin->id)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">You</span>
                                    @endif
                                </div>
                                <p class="truncate text-sm text-slate-500 dark:text-slate-400">{{ $superadmin->email }}</p>
                                <p class="mt-1 text-xs text-slate-400">Added {{ optional($superadmin->created_at)->diffForHumans() }}</p>
                            </div>
                        </div>

                        @if (auth()->id() !== $superadmin->id)
                            <form method="POST" action="{{ route('superadmin.users.destroy', $superadmin) }}" onsubmit="return confirm('Delete this superadmin account?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-600 transition hover:bg-red-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950/30">Delete</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
