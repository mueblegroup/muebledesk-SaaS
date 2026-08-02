<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">Edit user</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Update account details, role, verification and company access.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <form method="POST" action="{{ route('superadmin.users.update', $managedUser) }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <div><x-input-label for="name" value="Full name" /><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $managedUser->name)" required /><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                <div><x-input-label for="email" value="Email" /><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $managedUser->email)" required /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                <div><x-input-label for="phone" value="Phone" /><x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $managedUser->phone)" /></div>
                <div><x-input-label for="job_title" value="Job title" /><x-text-input id="job_title" name="job_title" class="mt-1 block w-full" :value="old('job_title', $managedUser->job_title)" /></div>

                <div>
                    <x-input-label for="role" value="System role" />
                    <select id="role" name="role" class="mt-1 block w-full" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $managedUser->role->value) === $role->value)>{{ str($role->value)->headline() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="membership_role" value="Company membership role" />
                    @php($currentMembershipRole = old('membership_role', optional($managedUser->companies->first()?->pivot)->role ?? 'member'))
                    <select id="membership_role" name="membership_role" class="mt-1 block w-full">
                        @foreach (['member' => 'Member', 'admin' => 'Company admin', 'owner' => 'Owner'] as $value => $label)
                            <option value="{{ $value }}" @selected($currentMembershipRole === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <x-input-label for="company_ids" value="Company access" />
                @php($selectedCompanies = collect(old('company_ids', $managedUser->companies->pluck('id')->all()))->map(fn($id)=>(int)$id)->all())
                <select id="company_ids" name="company_ids[]" multiple class="mt-1 block min-h-40 w-full">
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}" @selected(in_array($company->id, $selectedCompanies, true))>{{ $company->name }} ({{ $company->slug }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-400">Superadmins are platform-only and do not require company memberships.</p>
            </div>

            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700">
                <input type="hidden" name="email_verified" value="0">
                <input type="checkbox" name="email_verified" value="1" @checked(old('email_verified', (bool) $managedUser->email_verified_at))>
                <span><span class="block text-sm font-bold text-slate-900 dark:text-white">Email verified</span><span class="block text-xs text-slate-500">Unchecking this requires the user to verify again.</span></span>
            </label>

            <div class="grid gap-5 md:grid-cols-2">
                <div><x-input-label for="password" value="New password" /><x-text-input id="password" name="password" type="password" class="mt-1 block w-full" /><p class="mt-1 text-xs text-slate-400">Leave blank to keep the current password.</p><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
                <div><x-input-label for="password_confirmation" value="Confirm new password" /><x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" /></div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end dark:border-slate-800">
                <a href="{{ route('superadmin.users.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save user</button>
            </div>
        </form>
    </div>
</x-app-layout>
