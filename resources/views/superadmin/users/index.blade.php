<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">Platform users</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">View and manage every account across all companies.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($counts as $label => $count)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ str($label)->replace('_', ' ')->title() }}</p>
                    <p class="mt-2 text-3xl font-black text-slate-950 dark:text-white">{{ $count }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
            <form method="POST" action="{{ route('superadmin.users.store') }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @csrf
                <div>
                    <h2 class="text-lg font-extrabold text-slate-950 dark:text-white">Create user</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add any platform or company-level account.</p>
                </div>

                <div><x-input-label for="name" value="Full name" /><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                <div><x-input-label for="email" value="Email" /><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                <div><x-input-label for="phone" value="Phone" /><x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone')" /></div>
                <div><x-input-label for="job_title" value="Job title" /><x-text-input id="job_title" name="job_title" class="mt-1 block w-full" :value="old('job_title')" /></div>

                <div>
                    <x-input-label for="role" value="System role" />
                    <select id="role" name="role" class="mt-1 block w-full" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ str($role->value)->headline() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="company_ids" value="Companies" />
                    <select id="company_ids" name="company_ids[]" multiple class="mt-1 block min-h-32 w-full">
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(in_array($company->id, old('company_ids', [])))>{{ $company->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">Not required for superadmins. Hold Ctrl/Cmd to select multiple.</p>
                </div>

                <div>
                    <x-input-label for="membership_role" value="Company membership role" />
                    <select id="membership_role" name="membership_role" class="mt-1 block w-full">
                        @foreach (['member' => 'Member', 'admin' => 'Company admin', 'owner' => 'Owner'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('membership_role', 'member') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div><x-input-label for="password" value="Temporary password" /><x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required /><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
                <div><x-input-label for="password_confirmation" value="Confirm password" /><x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required /></div>
                <input type="hidden" name="email_verified" value="1">

                <button class="btn-primary w-full" type="submit">Create user</button>
            </form>

            <div class="space-y-5">
                <form method="GET" class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_180px_220px_auto] dark:border-slate-800 dark:bg-slate-900">
                    <input name="search" value="{{ request('search') }}" placeholder="Search name, email or phone">
                    <select name="role"><option value="">All roles</option>@foreach ($roles as $role)<option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ str($role->value)->headline() }}</option>@endforeach</select>
                    <select name="company_id"><option value="">All companies</option>@foreach ($companies as $company)<option value="{{ $company->id }}" @selected((int) request('company_id') === $company->id)>{{ $company->name }}</option>@endforeach</select>
                    <button class="btn-secondary" type="submit">Filter</button>
                </form>

                <div class="overflow-x-auto">
                    <table>
                        <thead><tr><th>User</th><th>Role</th><th>Companies</th><th>Verified</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr>
                                    <td><div class="font-extrabold text-slate-950 dark:text-white">{{ $user->name }} @if(auth()->id()===$user->id)<span class="ml-1 text-xs text-emerald-600">You</span>@endif</div><div class="text-xs text-slate-500">{{ $user->email }}</div>@if($user->phone)<div class="text-xs text-slate-400">{{ $user->phone }}</div>@endif</td>
                                    <td><span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ str($user->role->value)->headline() }}</span></td>
                                    <td><div class="max-w-xs text-xs text-slate-600 dark:text-slate-300">{{ $user->companies->pluck('name')->join(', ') ?: 'Platform only' }}</div></td>
                                    <td>{{ $user->email_verified_at ? 'Yes' : 'No' }}</td>
                                    <td>{{ optional($user->created_at)->format('d M Y') }}</td>
                                    <td><div class="flex justify-end gap-2"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('superadmin.users.edit', $user) }}">Edit</a>@if(auth()->id() !== $user->id)<form method="POST" action="{{ route('superadmin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user? Accounts with company ownership or business records cannot be deleted.')">@csrf @method('DELETE')<button class="btn-danger !px-3 !py-2 text-xs" type="submit">Delete</button></form>@endif</div></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-10 text-center text-slate-500">No users found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $users->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
