<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">e-Invoice / MyInvois Setup</h2>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Feature</p>
                <p class="mt-2 text-lg font-black {{ $enabled ? 'text-emerald-600' : 'text-slate-600' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Environment</p>
                <p class="mt-2 text-lg font-black">{{ strtoupper($environment) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Credentials</p>
                <p class="mt-2 text-lg font-black {{ $credentialsReady ? 'text-emerald-600' : 'text-amber-600' }}">{{ $credentialsReady ? 'Configured' : 'Missing' }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Connection</p>
                <p class="mt-2 text-lg font-black {{ $lastTestStatus === 'connected' ? 'text-emerald-600' : ($lastTestStatus === 'failed' ? 'text-red-600' : 'text-slate-600') }}">
                    {{ $lastTestStatus === 'connected' ? 'Connected' : ($lastTestStatus === 'failed' ? 'Failed' : 'Not tested') }}
                </p>
            </div>
        </div>

        @if($lastTestMessage)
            <div class="rounded-3xl border {{ $lastTestStatus === 'connected' ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950' : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950' }} p-5">
                <p class="font-bold">Last connection test{{ $lastTestedAt ? ' · '.$lastTestedAt : '' }}</p>
                <p class="mt-1 text-sm">{{ $lastTestMessage }}</p>
            </div>
        @endif

        <div class="rounded-3xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950">
            <h3 class="font-extrabold text-indigo-950 dark:text-indigo-100">Setup flow</h3>
            <div class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                <div><span class="font-black">1.</span> Register this company ERP in the MyInvois Portal.</div>
                <div><span class="font-black">2.</span> Paste the company-specific Client ID and Client Secret below.</div>
                <div><span class="font-black">3.</span> Complete the supplier taxpayer identity.</div>
                <div><span class="font-black">4.</span> Save, then run Test Connection before live submission.</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.einvoice-settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-extrabold">Integration</h3>
                        <p class="text-sm text-slate-500">Credentials are encrypted and isolated to this company workspace.</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-bold">
                        <input type="checkbox" name="myinvois_enabled" value="1" @checked(old('myinvois_enabled', $enabled))>
                        Enable e-Invoice for this company
                    </label>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <x-input-label for="myinvois_environment" value="Active environment"/>
                        <select id="myinvois_environment" name="myinvois_environment" class="mt-1 block w-full rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                            <option value="sandbox" @selected(old('myinvois_environment', $environment) === 'sandbox')>Sandbox / Pre-production</option>
                            <option value="production" @selected(old('myinvois_environment', $environment) === 'production')>Production</option>
                        </select>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-950">
                        Production submissions are still protected by the platform-level production safety switch. Selecting Production here does not bypass that protection.
                    </div>
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                        <h4 class="font-black">Sandbox credentials</h4>
                        <div class="mt-3 space-y-3">
                            <div><x-input-label for="myinvois_sandbox_client_id" value="Client ID"/><x-text-input id="myinvois_sandbox_client_id" name="myinvois_sandbox_client_id" class="mt-1 block w-full" :value="old('myinvois_sandbox_client_id', $sandboxClientId)" autocomplete="off"/></div>
                            <div><x-input-label for="myinvois_sandbox_client_secret" value="Client Secret"/><x-text-input id="myinvois_sandbox_client_secret" name="myinvois_sandbox_client_secret" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep existing secret" autocomplete="new-password"/></div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                        <h4 class="font-black">Production credentials</h4>
                        <div class="mt-3 space-y-3">
                            <div><x-input-label for="myinvois_production_client_id" value="Client ID"/><x-text-input id="myinvois_production_client_id" name="myinvois_production_client_id" class="mt-1 block w-full" :value="old('myinvois_production_client_id', $productionClientId)" autocomplete="off"/></div>
                            <div><x-input-label for="myinvois_production_client_secret" value="Client Secret"/><x-text-input id="myinvois_production_client_secret" name="myinvois_production_client_secret" type="password" class="mt-1 block w-full" placeholder="Leave blank to keep existing secret" autocomplete="new-password"/></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <h3 class="text-lg font-extrabold">Supplier taxpayer profile</h3>
                    <p class="text-sm text-slate-500">This identity is sent as the supplier on e-Invoices. Tenant workspaces do not inherit supplier fields from the server environment.</p>
                </div>

                @if($missing)
                    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950">
                        <span class="font-bold">Setup incomplete:</span> {{ implode(', ', $missing) }}
                    </div>
                @endif

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div><x-input-label for="myinvois_supplier_tin" value="Supplier TIN"/><x-text-input id="myinvois_supplier_tin" name="myinvois_supplier_tin" class="mt-1 block w-full" :value="old('myinvois_supplier_tin', $profile['tin'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_registration_type" value="Registration Type"/><select id="myinvois_supplier_registration_type" name="myinvois_supplier_registration_type" class="mt-1 block w-full rounded-2xl border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach(['BRN'=>'Business Registration Number','NRIC'=>'NRIC','PASSPORT'=>'Passport','ARMY'=>'Army'] as $value=>$label)<option value="{{ $value }}" @selected(old('myinvois_supplier_registration_type', $profile['registration_type'] ?? 'BRN') === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div><x-input-label for="myinvois_supplier_registration_number" value="Registration / ID Number"/><x-text-input id="myinvois_supplier_registration_number" name="myinvois_supplier_registration_number" class="mt-1 block w-full" :value="old('myinvois_supplier_registration_number', $profile['registration_number'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_name" value="Registered Supplier Name"/><x-text-input id="myinvois_supplier_name" name="myinvois_supplier_name" class="mt-1 block w-full" :value="old('myinvois_supplier_name', $profile['name'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_msic_code" value="MSIC Code"/><x-text-input id="myinvois_supplier_msic_code" name="myinvois_supplier_msic_code" maxlength="5" class="mt-1 block w-full" :value="old('myinvois_supplier_msic_code', $profile['msic_code'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_business_activity" value="Business Activity"/><x-text-input id="myinvois_supplier_business_activity" name="myinvois_supplier_business_activity" class="mt-1 block w-full" :value="old('myinvois_supplier_business_activity', $profile['business_activity'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_sst_number" value="SST Registration Number"/><x-text-input id="myinvois_supplier_sst_number" name="myinvois_supplier_sst_number" class="mt-1 block w-full" :value="old('myinvois_supplier_sst_number', $profile['sst_number'] ?? 'NA')"/></div>
                    <div><x-input-label for="myinvois_supplier_ttx_number" value="Tourism Tax Number"/><x-text-input id="myinvois_supplier_ttx_number" name="myinvois_supplier_ttx_number" class="mt-1 block w-full" :value="old('myinvois_supplier_ttx_number', $profile['ttx_number'] ?? 'NA')"/></div>
                    <div><x-input-label for="myinvois_supplier_email" value="Supplier Email"/><x-text-input id="myinvois_supplier_email" type="email" name="myinvois_supplier_email" class="mt-1 block w-full" :value="old('myinvois_supplier_email', $profile['email'] ?? '')"/></div>
                    <div><x-input-label for="myinvois_supplier_phone" value="Supplier Phone"/><x-text-input id="myinvois_supplier_phone" name="myinvois_supplier_phone" class="mt-1 block w-full" :value="old('myinvois_supplier_phone', $profile['phone'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_address_line_1" value="Address Line 1"/><x-text-input id="myinvois_supplier_address_line_1" name="myinvois_supplier_address_line_1" class="mt-1 block w-full" :value="old('myinvois_supplier_address_line_1', $profile['address_line_1'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_address_line_2" value="Address Line 2"/><x-text-input id="myinvois_supplier_address_line_2" name="myinvois_supplier_address_line_2" class="mt-1 block w-full" :value="old('myinvois_supplier_address_line_2', $profile['address_line_2'] ?? '')"/></div>
                    <div><x-input-label for="myinvois_supplier_city" value="City"/><x-text-input id="myinvois_supplier_city" name="myinvois_supplier_city" class="mt-1 block w-full" :value="old('myinvois_supplier_city', $profile['city'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_state_code" value="MyInvois State Code"/><x-text-input id="myinvois_supplier_state_code" name="myinvois_supplier_state_code" maxlength="3" class="mt-1 block w-full" :value="old('myinvois_supplier_state_code', $profile['state_code'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_postcode" value="Postcode"/><x-text-input id="myinvois_supplier_postcode" name="myinvois_supplier_postcode" class="mt-1 block w-full" :value="old('myinvois_supplier_postcode', $profile['postcode'] ?? '')" required/></div>
                    <div><x-input-label for="myinvois_supplier_country_code" value="Country Code"/><x-text-input id="myinvois_supplier_country_code" name="myinvois_supplier_country_code" maxlength="3" class="mt-1 block w-full" :value="old('myinvois_supplier_country_code', $profile['country_code'] ?? 'MYS')" required/></div>
                </div>
            </section>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn-primary">Save e-Invoice Setup</button>
                <a href="{{ route('admin.setting.index') }}" class="btn-secondary">General Settings</a>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.einvoice-settings.test') }}" class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div><h3 class="font-extrabold">Connection test</h3><p class="text-sm text-slate-500">Authenticates with the active environment and validates this supplier TIN against the configured registration identity.</p></div>
                <button type="submit" class="btn-secondary">Test Connection</button>
            </div>
        </form>
    </div>
</x-app-layout>
