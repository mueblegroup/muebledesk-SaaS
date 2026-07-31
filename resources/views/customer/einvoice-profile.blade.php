<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">My e-Invoice Profile</h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="rounded-3xl border border-indigo-200 bg-indigo-50/70 p-5 dark:border-indigo-900 dark:bg-indigo-950/30">
            <h3 class="text-lg font-extrabold text-indigo-950 dark:text-indigo-100">Verify your MyInvois identity</h3>
            <p class="mt-1 text-sm text-indigo-800 dark:text-indigo-300">Enter your NRIC, then use the lookup button. MyInvois can return and verify your TIN, but it does not provide your legal name or address.</p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label for="id_number" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">NRIC</label>
                    <input form="einvoice-profile-form" id="id_number" name="id_number" type="text" value="{{ old('id_number', $client->id_number) }}" class="block w-full" placeholder="12 digits without hyphens" required>
                </div>
                <button id="myinvois-lookup-button" type="button" class="btn-primary">Search NRIC in MyInvois</button>
            </div>
            <div id="myinvois-lookup-message" class="mt-4 hidden rounded-2xl border px-4 py-3 text-sm font-semibold"></div>
        </div>

        <form id="einvoice-profile-form" method="POST" action="{{ route('customer.einvoice-profile.update') }}" class="space-y-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @method('PUT')

            <section class="space-y-5">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-950 dark:text-white">Identity and contact</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use your full legal name as shown on MyKad.</p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Full legal name</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $client->name) }}" class="block w-full" required>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="tin_number" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">TIN</label>
                        <input id="tin_number" name="tin_number" type="text" value="{{ old('tin_number', $client->tin_number) }}" class="block w-full uppercase" readonly>
                        <x-input-error :messages="$errors->get('tin_number')" class="mt-2" />
                    </div>
                    <div>
                        <label for="billing_email" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Billing email</label>
                        <input id="billing_email" name="billing_email" type="email" value="{{ old('billing_email', $client->billing_email ?: $client->email) }}" class="block w-full">
                    </div>
                    <div>
                        <label for="phone" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Phone</label>
                        <input id="phone" name="phone" type="text" value="{{ old('phone', $client->phone) }}" class="block w-full">
                    </div>
                </div>
                <input id="id_type" name="id_type" type="hidden" value="NRIC">
                <input id="country_code" name="country_code" type="hidden" value="MYS">
            </section>

            <section class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-950 dark:text-white">Billing address</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This address will be used for your e-Invoices.</p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="address_line_1" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Address line 1</label>
                        <input id="address_line_1" name="address_line_1" type="text" value="{{ old('address_line_1', $client->address_line_1) }}" class="block w-full" required>
                    </div>
                    <div class="md:col-span-2">
                        <label for="address_line_2" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Address line 2</label>
                        <input id="address_line_2" name="address_line_2" type="text" value="{{ old('address_line_2', $client->address_line_2) }}" class="block w-full">
                    </div>
                    <div>
                        <label for="postcode" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Postcode</label>
                        <input id="postcode" name="postcode" type="text" value="{{ old('postcode', $client->postcode) }}" class="block w-full" required>
                    </div>
                    <div>
                        <label for="city" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">City</label>
                        <input id="city" name="city" type="text" value="{{ old('city', $client->city) }}" class="block w-full" required>
                    </div>
                    <div>
                        <label for="state" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">State</label>
                        <select id="state" name="state" class="block w-full" required>
                            <option value="">Select state</option>
                            @foreach(['01'=>'Johor','02'=>'Kedah','03'=>'Kelantan','04'=>'Melaka','05'=>'Negeri Sembilan','06'=>'Pahang','07'=>'Pulau Pinang','08'=>'Perak','09'=>'Perlis','10'=>'Selangor','11'=>'Terengganu','12'=>'Sabah','13'=>'Sarawak','14'=>'W.P. Kuala Lumpur','15'=>'W.P. Labuan','16'=>'W.P. Putrajaya','17'=>'Not Applicable'] as $code => $label)
                                <option value="{{ $code }}" @selected(old('state', $client->state) === $code)>{{ $label }} ({{ $code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Country</label>
                        <input type="text" value="Malaysia (MYS)" class="block w-full" disabled>
                    </div>
                </div>
            </section>

            <div class="flex justify-end border-t border-slate-200 pt-6 dark:border-slate-800">
                <button type="submit" class="btn-primary">Save e-Invoice Profile</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const button = document.getElementById('myinvois-lookup-button');
        const message = document.getElementById('myinvois-lookup-message');
        if (!button || !message) return;

        const showMessage = (text, success = false) => {
            message.textContent = text;
            message.className = 'mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold ' + (success
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300');
        };

        button.addEventListener('click', async function () {
            const idNumber = (document.getElementById('id_number').value || '').replace(/[^A-Za-z0-9]/g, '');
            if (!idNumber) return showMessage('Enter your NRIC first.');

            button.disabled = true;
            button.textContent = 'Searching…';
            showMessage('Contacting MyInvois {{ strtoupper(config('myinvois.environment')) }}…');

            try {
                const response = await fetch(@js(route('myinvois.taxpayers.search')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ id_type: 'NRIC', id_number: idNumber }),
                });
                const contentType = response.headers.get('content-type') || '';
                const data = contentType.includes('application/json') ? await response.json() : { message: await response.text() };
                if (!response.ok) throw new Error(data.message || `Lookup failed (${response.status}).`);

                document.getElementById('id_number').value = data.id_number;
                document.getElementById('tin_number').value = data.tin;
                document.getElementById('id_type').value = 'NRIC';
                document.getElementById('country_code').value = 'MYS';
                showMessage(`${data.message} TIN: ${data.tin} (${data.environment})`, Boolean(data.verified));
            } catch (error) {
                console.error('MyInvois taxpayer lookup failed', error);
                showMessage(error.message || 'Taxpayer lookup failed.');
            } finally {
                button.disabled = false;
                button.textContent = 'Search NRIC in MyInvois';
            }
        });
    });
    </script>
    @endpush
</x-app-layout>
