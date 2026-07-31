@php
    $client = $client ?? null;
    $clientType = old('client_type', $client->client_type ?? 'company');
    $idType = old('id_type', $client->id_type ?? 'BRN');
    $requiredBadge = '<span class="ml-2 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-red-600 dark:bg-red-950 dark:text-red-300">Required</span>';
@endphp

<section class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
    <div>
        <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Basic & Contact Details</h4>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Primary billing contact and customer portal identity.</p>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div>
            <label for="name" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Client / Company Name {!! $requiredBadge !!}</label>
            <input id="name" name="name" type="text" value="{{ old('name', $client->name ?? '') }}" required autofocus class="block w-full">
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <label for="client_type" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Client Type {!! $requiredBadge !!}</label>
            <select id="client_type" name="client_type" required class="block w-full">
                @foreach(['company' => 'Company', 'individual' => 'Individual', 'government' => 'Government', 'non_profit' => 'Non-Profit'] as $value => $label)
                    <option value="{{ $value }}" @selected($clientType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('client_type')" class="mt-2" />
        </div>

        <div>
            <label for="contact_person" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Contact Person <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <input id="contact_person" name="contact_person" type="text" value="{{ old('contact_person', $client->contact_person ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('contact_person')" class="mt-2" />
        </div>

        <div>
            <label for="email" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Primary Email {!! $requiredBadge !!}</label>
            <input id="email" name="email" type="email" value="{{ old('email', $client->email ?? '') }}" required class="block w-full">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <label for="billing_email" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Billing Email <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <input id="billing_email" name="billing_email" type="email" value="{{ old('billing_email', $client->billing_email ?? '') }}" class="block w-full" placeholder="Defaults to primary email if empty">
            <x-input-error :messages="$errors->get('billing_email')" class="mt-2" />
        </div>

        <div>
            <label for="phone" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Phone <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <input id="phone" name="phone" type="text" value="{{ old('phone', $client->phone ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div class="md:col-span-2 xl:col-span-3">
            <label for="website" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Website <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <input id="website" name="website" type="url" value="{{ old('website', $client->website ?? '') }}" class="block w-full" placeholder="https://example.com">
            <x-input-error :messages="$errors->get('website')" class="mt-2" />
        </div>
    </div>
</section>

@if (! $client)
    <section class="space-y-4 border-t border-slate-200 pt-8 dark:border-slate-800">
        <div>
            <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Customer Portal Access</h4>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create the customer account now and optionally email a secure password setup link.</p>
        </div>
        <label class="flex gap-3 rounded-3xl border border-indigo-100 bg-indigo-50/70 p-4 text-sm text-slate-700 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-slate-200">
            <input type="checkbox" name="send_password_setup_link" value="1" @checked(old('send_password_setup_link')) class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block font-extrabold text-slate-950 dark:text-white">Send password setup email to this client</span>
                <span class="mt-1 block text-slate-500 dark:text-slate-400">The customer receives a secure reset-password link and can set their own password. No temporary password will be shown.</span>
            </span>
        </label>
    </section>
@endif

<section class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
    <div>
        <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Tax & E-Invoice Identity</h4>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">These fields prepare the client record for Malaysian e-invoice requirements.</p>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div>
            <label for="tin_number" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">TIN Number <span class="text-xs font-medium text-slate-400">Optional for now</span></label>
            <input id="tin_number" name="tin_number" type="text" value="{{ old('tin_number', $client->tin_number ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('tin_number')" class="mt-2" />
        </div>

        <div>
            <label for="id_type" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">ID Type <span class="text-xs font-medium text-slate-400">Optional for now</span></label>
            <select id="id_type" name="id_type" class="block w-full">
                @foreach(['BRN' => 'Business Registration No.', 'NRIC' => 'NRIC', 'PASSPORT' => 'Passport', 'ARMY' => 'Army / Police', 'OTHER' => 'Other'] as $value => $label)
                    <option value="{{ $value }}" @selected($idType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('id_type')" class="mt-2" />
        </div>

        <div>
            <label for="id_number" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">ID / Registration Number <span class="text-xs font-medium text-slate-400">Optional for now</span></label>
            <input id="id_number" name="id_number" type="text" value="{{ old('id_number', $client->id_number ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('id_number')" class="mt-2" />
        </div>

        <div>
            <label for="sst_registration_number" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">SST Registration Number <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <input id="sst_registration_number" name="sst_registration_number" type="text" value="{{ old('sst_registration_number', $client->sst_registration_number ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('sst_registration_number')" class="mt-2" />
        </div>

        <div>
            <label for="payment_terms_days" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Payment Terms Days <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <input id="payment_terms_days" name="payment_terms_days" type="number" min="0" max="365" value="{{ old('payment_terms_days', $client->payment_terms_days ?? '') }}" class="block w-full" placeholder="Example: 14">
            <x-input-error :messages="$errors->get('payment_terms_days')" class="mt-2" />
        </div>
    </div>
</section>

<section class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
    <div>
        <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Billing Address</h4>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Structured address is used for documents and future e-invoice payloads.</p>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <div class="md:col-span-2 xl:col-span-3">
            <label for="address_line_1" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Address Line 1 <span class="text-xs font-medium text-slate-400">Optional for now</span></label>
            <input id="address_line_1" name="address_line_1" type="text" value="{{ old('address_line_1', $client->address_line_1 ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('address_line_1')" class="mt-2" />
        </div>

        <div class="md:col-span-2 xl:col-span-3">
            <label for="address_line_2" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Address Line 2 <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <input id="address_line_2" name="address_line_2" type="text" value="{{ old('address_line_2', $client->address_line_2 ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('address_line_2')" class="mt-2" />
        </div>

        <div>
            <label for="city" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">City <span class="text-xs font-medium text-slate-400">Optional for now</span></label>
            <input id="city" name="city" type="text" value="{{ old('city', $client->city ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('city')" class="mt-2" />
        </div>

        <div>
            <label for="state" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">State <span class="text-xs font-medium text-slate-400">Optional for now</span></label>
            <input id="state" name="state" type="text" value="{{ old('state', $client->state ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('state')" class="mt-2" />
        </div>

        <div>
            <label for="postcode" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Postcode <span class="text-xs font-medium text-slate-400">Optional for now</span></label>
            <input id="postcode" name="postcode" type="text" value="{{ old('postcode', $client->postcode ?? '') }}" class="block w-full">
            <x-input-error :messages="$errors->get('postcode')" class="mt-2" />
        </div>

        <div>
            <label for="country_code" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Country Code <span class="text-xs font-medium text-slate-400">Defaults to MY</span></label>
            <input id="country_code" name="country_code" type="text" maxlength="2" value="{{ old('country_code', $client->country_code ?? 'MY') }}" class="block w-full uppercase">
            <x-input-error :messages="$errors->get('country_code')" class="mt-2" />
        </div>

        <div class="md:col-span-2 xl:col-span-3">
            <label for="address" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Legacy / Full Address Override <span class="text-xs font-medium text-slate-400">Optional</span></label>
            <textarea id="address" name="address" rows="3" class="block w-full" placeholder="Optional. Structured address above will be used if filled.">{{ old('address', $client->address ?? '') }}</textarea>
            <x-input-error :messages="$errors->get('address')" class="mt-2" />
        </div>
    </div>
</section>

<section class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
    <div>
        <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Internal Notes</h4>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">These notes are internal and should not appear on invoices.</p>
    </div>

    <div>
        <textarea id="notes" name="notes" rows="4" class="block w-full" placeholder="Examples: billing preferences, contact timing, internal reminders">{{ old('notes', $client->notes ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>
</section>

<div class="sticky bottom-0 -mx-4 border-t border-slate-200 bg-white/90 px-4 py-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500 dark:text-slate-400"><span class="font-bold text-red-600">Required:</span> client name, client type, and primary email. E-invoice fields can be completed later.</p>
        <div class="flex gap-3">
            <a href="{{ route('clients.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">{{ $client ? 'Update Client' : 'Create Client' }}</button>
        </div>
    </div>
</div>