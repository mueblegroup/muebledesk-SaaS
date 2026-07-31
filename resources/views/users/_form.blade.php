@php
    $client = $user->clients ?? null;
    $selectedRole = old('role', $user->role?->value ?? $user->getRawOriginal('role') ?? '');
@endphp

<div x-data="{ role: @js($selectedRole) }" class="space-y-8">
    <section class="space-y-5">
        <div>
            <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Account Details</h4>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Login, contact, and role information.</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="name" class="mb-2 block text-sm font-bold">Full Name / Company Name *</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="block w-full">
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div>
                <label for="email" class="mb-2 block text-sm font-bold">Login Email *</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="block w-full">
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <label for="phone" class="mb-2 block text-sm font-bold">Phone Number *</label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required class="block w-full">
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <label for="role" class="mb-2 block text-sm font-bold">Role *</label>
                <select id="role" name="role" x-model="role" required class="block w-full">
                    <option value="">Select role</option>
                    @foreach($roles as $roleOption)
                        <option value="{{ $roleOption->value }}">{{ ucfirst($roleOption->value) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-2" />
            </div>
            <div x-show="role !== 'customer'" x-cloak>
                <label for="job_title" class="mb-2 block text-sm font-bold">Job Title *</label>
                <input id="job_title" name="job_title" value="{{ old('job_title', $user->job_title) }}" :required="role !== 'customer'" class="block w-full">
                <x-input-error :messages="$errors->get('job_title')" class="mt-2" />
            </div>
            <div class="md:col-span-2">
                <label for="address" class="mb-2 block text-sm font-bold">Address *</label>
                <textarea id="address" name="address" rows="3" required class="block w-full">{{ old('address', $user->address ?? $client?->address) }}</textarea>
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
        </div>
    </section>

    <section x-show="role === 'customer'" x-cloak class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
        <div>
            <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Customer Billing Profile</h4>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Required for invoices, payment links, and e-Invoice records.</p>
        </div>
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div><label class="mb-2 block text-sm font-bold">Client Type *</label><select name="client_type" :required="role === 'customer'" class="block w-full"><option value="individual" @selected(old('client_type', $client?->client_type) === 'individual')>Individual</option><option value="company" @selected(old('client_type', $client?->client_type) === 'company')>Company</option></select></div>
            <div><label class="mb-2 block text-sm font-bold">Contact Person</label><input name="contact_person" value="{{ old('contact_person', $client?->contact_person) }}" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">Billing Email *</label><input name="billing_email" type="email" value="{{ old('billing_email', $client?->billing_email ?? $user->email) }}" :required="role === 'customer'" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">Website</label><input name="website" type="url" value="{{ old('website', $client?->website) }}" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">Address Line 1 *</label><input name="address_line_1" value="{{ old('address_line_1', $client?->address_line_1) }}" :required="role === 'customer'" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">Address Line 2</label><input name="address_line_2" value="{{ old('address_line_2', $client?->address_line_2) }}" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">City *</label><input name="city" value="{{ old('city', $client?->city) }}" :required="role === 'customer'" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">State *</label><input name="state" value="{{ old('state', $client?->state) }}" :required="role === 'customer'" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">Postcode *</label><input name="postcode" value="{{ old('postcode', $client?->postcode) }}" :required="role === 'customer'" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">Country Code *</label><input name="country_code" maxlength="3" value="{{ old('country_code', $client?->country_code ?? 'MYS') }}" :required="role === 'customer'" class="block w-full uppercase"></div>
            <div><label class="mb-2 block text-sm font-bold">TIN Number</label><input name="tin_number" value="{{ old('tin_number', $client?->tin_number) }}" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">ID Type</label><select name="id_type" class="block w-full"><option value="">Select</option>@foreach(['NRIC','BRN','PASSPORT','ARMY'] as $type)<option value="{{ $type }}" @selected(old('id_type', $client?->id_type) === $type)>{{ $type }}</option>@endforeach</select></div>
            <div><label class="mb-2 block text-sm font-bold">ID / Registration Number</label><input name="id_number" value="{{ old('id_number', $client?->id_number) }}" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">SST Registration Number</label><input name="sst_registration_number" value="{{ old('sst_registration_number', $client?->sst_registration_number) }}" class="block w-full"></div>
            <div><label class="mb-2 block text-sm font-bold">Payment Terms (Days)</label><input name="payment_terms_days" type="number" min="0" max="365" value="{{ old('payment_terms_days', $client?->payment_terms_days ?? 14) }}" class="block w-full"></div>
            <div class="md:col-span-2 xl:col-span-3"><label class="mb-2 block text-sm font-bold">Notes</label><textarea name="notes" rows="3" class="block w-full">{{ old('notes', $client?->notes) }}</textarea></div>
        </div>
    </section>

    <section class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
        <div><h4 class="text-lg font-extrabold text-slate-950 dark:text-white">{{ $user->exists ? 'Password Reset' : 'Account Password' }}</h4></div>
        <div class="grid gap-5 md:grid-cols-2">
            <div><label class="mb-2 block text-sm font-bold">Password {{ $user->exists ? '' : '*' }}</label><input name="password" type="password" {{ $user->exists ? '' : 'required' }} class="block w-full"><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
            <div><label class="mb-2 block text-sm font-bold">Confirm Password {{ $user->exists ? '' : '*' }}</label><input name="password_confirmation" type="password" {{ $user->exists ? '' : 'required' }} class="block w-full"></div>
        </div>
    </section>
</div>
