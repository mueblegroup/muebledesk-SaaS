<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Client Details') }}: {{ $client->name }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="section-title">Client Profile</h3>
                <p class="section-subtitle">Billing, tax, address, and portal identity details.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if ($client->user)
                    <form method="POST" action="{{ route('clients.send_password_setup_link', $client) }}" onsubmit="return confirm('Send a password setup/reset link to {{ $client->user->email }}?');">
                        @csrf
                        <button type="submit" class="btn-secondary">Send Password Link</button>
                    </form>
                @endif
                <a href="{{ route('clients.edit', $client) }}" class="btn-primary">Edit Client</a>
                <a href="{{ route('clients.index') }}" class="btn-secondary">Back</a>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                <div>
                    <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Basic Details</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Main billing and communication information.</p>
                </div>
                <dl class="grid gap-4 md:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Name</dt><dd class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $client->name }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Type</dt><dd class="mt-1">{{ Str::title(str_replace('_', ' ', $client->client_type ?? 'company')) }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Contact Person</dt><dd class="mt-1">{{ $client->contact_person ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Phone</dt><dd class="mt-1">{{ $client->phone ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Primary Email</dt><dd class="mt-1">{{ $client->email }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Billing Email</dt><dd class="mt-1">{{ $client->billing_email ?: $client->email }}</dd></div>
                    <div class="md:col-span-2"><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Website</dt><dd class="mt-1">{{ $client->website ?: 'N/A' }}</dd></div>
                </dl>
            </section>

            <section class="space-y-4 rounded-3xl border border-indigo-100 bg-indigo-50/70 p-6 shadow-sm dark:border-indigo-900 dark:bg-indigo-950/40">
                <div>
                    <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Customer Portal</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Use this when a customer needs access or forgot their password.</p>
                </div>
                <dl class="space-y-4">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Portal User</dt><dd class="mt-1">{{ $client->user?->email ?? 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Access Link</dt><dd class="mt-1 text-sm text-slate-600 dark:text-slate-300">Send a setup/reset link so the customer can create a new password securely.</dd></div>
                </dl>
                @if ($client->user)
                    <form method="POST" action="{{ route('clients.send_password_setup_link', $client) }}" onsubmit="return confirm('Send a password setup/reset link to {{ $client->user->email }}?');">
                        @csrf
                        <button type="submit" class="btn-primary w-full">Send Password Setup Link</button>
                    </form>
                @else
                    <p class="rounded-2xl bg-white p-3 text-sm font-semibold text-amber-700 dark:bg-slate-900 dark:text-amber-300">No linked customer portal user found.</p>
                @endif
            </section>

            <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Tax Identity</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Used for invoices and e-invoice readiness.</p>
                </div>
                <dl class="space-y-4">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">TIN Number</dt><dd class="mt-1">{{ $client->tin_number ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">ID Type</dt><dd class="mt-1">{{ $client->id_type ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">ID / Registration Number</dt><dd class="mt-1">{{ $client->id_number ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">SST Number</dt><dd class="mt-1">{{ $client->sst_registration_number ?: 'N/A' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Payment Terms</dt><dd class="mt-1">{{ $client->payment_terms_days !== null ? $client->payment_terms_days.' day(s)' : 'Default' }}</dd></div>
                </dl>
            </section>

            <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                <div>
                    <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Billing Address</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Structured billing address for documents.</p>
                </div>
                <p class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $client->billing_address ?: 'Address not provided' }}</p>
            </section>

            <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Record Info</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Internal metadata.</p>
                </div>
                <dl class="space-y-4">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Created</dt><dd class="mt-1">{{ $client->created_at->format('Y-m-d H:i:s') }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-500">Updated</dt><dd class="mt-1">{{ $client->updated_at->format('Y-m-d H:i:s') }}</dd></div>
                </dl>
            </section>

            <section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-3">
                <div>
                    <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">Internal Notes</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Not shown on invoices or quotations.</p>
                </div>
                <p class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $client->notes ?: 'No internal notes.' }}</p>
            </section>
        </div>
    </div>
</x-app-layout>