<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">API Guide</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Connect n8n, internal tools, mobile apps, or other systems to your MuebleDesk company workspace.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="grid gap-4 lg:grid-cols-3">
            <div class="card hover:translate-y-0 lg:col-span-2">
                <p class="text-xs font-extrabold uppercase tracking-[0.15em] text-indigo-600 dark:text-indigo-400">API v1</p>
                <h3 class="mt-2 text-2xl font-extrabold text-slate-950 dark:text-white">Base URL</h3>
                <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-sm text-emerald-300"><code>{{ $baseUrl }}</code></pre>
                <p class="mt-4 text-sm text-slate-600 dark:text-slate-300">Every API key belongs to the current company. The API automatically applies that company as the tenant, so records from another company are not returned.</p>
            </div>
            <div class="card hover:translate-y-0">
                <h3 class="text-lg font-extrabold text-slate-950 dark:text-white">Before you start</h3>
                <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
                    <li>Create an API key.</li>
                    <li>Select only the permissions the integration needs.</li>
                    <li>Copy the key immediately; the full key is shown only once.</li>
                    <li>Store it as a secret in n8n or your application.</li>
                </ol>
                <a href="{{ route('admin.api-keys.index') }}" class="btn-primary mt-5 w-full">Manage API Keys</a>
            </div>
        </section>

        <section class="card hover:translate-y-0">
            <h3 class="text-xl font-extrabold text-slate-950 dark:text-white">Authentication</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Use either a Bearer token or the <code>X-API-Key</code> header. Bearer authentication is recommended.</p>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div>
                    <p class="mb-2 text-xs font-extrabold uppercase tracking-wide text-slate-500">Bearer token</p>
                    <pre class="overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300"><code>Authorization: Bearer mdk_your_api_key
Accept: application/json
Content-Type: application/json</code></pre>
                </div>
                <div>
                    <p class="mb-2 text-xs font-extrabold uppercase tracking-wide text-slate-500">Alternative header</p>
                    <pre class="overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300"><code>X-API-Key: mdk_your_api_key
Accept: application/json
Content-Type: application/json</code></pre>
                </div>
            </div>
        </section>

        <section class="card hover:translate-y-0">
            <h3 class="text-xl font-extrabold text-slate-950 dark:text-white">Quick test</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">The health endpoint does not require an API key. Use it first to verify that the API is reachable.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300"><code>curl "{{ $baseUrl }}/health"</code></pre>
            <p class="mt-5 text-sm font-bold text-slate-700 dark:text-slate-200">List clients</p>
            <pre class="mt-2 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300"><code>curl "{{ $baseUrl }}/clients?per_page=25" \
  -H "Authorization: Bearer mdk_your_api_key" \
  -H "Accept: application/json"</code></pre>
        </section>

        <section class="card hover:translate-y-0">
            <h3 class="text-xl font-extrabold text-slate-950 dark:text-white">Create a client</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Requires <code>clients.write</code>.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300"><code>curl -X POST "{{ $baseUrl }}/clients" \
  -H "Authorization: Bearer mdk_your_api_key" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Example Customer Sdn Bhd",
    "client_type": "company",
    "email": "accounts@example.com",
    "phone": "+60123456789",
    "country_code": "MY",
    "tin_number": "C1234567890",
    "id_type": "brn",
    "id_number": "202601234567",
    "payment_terms_days": 30
  }'</code></pre>
        </section>

        <section class="card hover:translate-y-0">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-xl font-extrabold text-slate-950 dark:text-white">Available endpoints</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Permissions shown here must be included when the API key is created.</p>
                </div>
            </div>

            <div class="mt-5 space-y-6">
                @foreach ($endpointGroups as $group => $endpoints)
                    <div>
                        <h4 class="mb-3 text-sm font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $group }}</h4>
                        <div class="overflow-x-auto">
                            <table>
                                <thead><tr><th>Method</th><th>Endpoint</th><th>Permission</th><th>Purpose</th></tr></thead>
                                <tbody>
                                    @foreach ($endpoints as [$method, $endpoint, $permission, $purpose])
                                        <tr>
                                            <td><span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-black text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $method }}</span></td>
                                            <td class="font-mono text-xs">{{ $endpoint }}</td>
                                            <td class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ $permission }}</td>
                                            <td>{{ $purpose }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="card hover:translate-y-0">
                <h3 class="text-xl font-extrabold text-slate-950 dark:text-white">Pagination & search</h3>
                <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <p>List endpoints return paginated data. Where supported, use <code>per_page</code>; the maximum page size is 100.</p>
                    <p>Clients support <code>q</code> for name, email, phone and TIN searches.</p>
                </div>
                <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300"><code>{{ $baseUrl }}/clients?q=acme&amp;per_page=50</code></pre>
            </div>
            <div class="card hover:translate-y-0">
                <h3 class="text-xl font-extrabold text-slate-950 dark:text-white">Common responses</h3>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    @foreach ([200 => 'Success', 201 => 'Created', 401 => 'Missing/invalid key', 403 => 'Permission denied', 409 => 'Record conflict', 422 => 'Validation error'] as $status => $meaning)
                        <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-950/60"><span class="font-black">{{ $status }}</span><p class="mt-1 text-xs text-slate-500">{{ $meaning }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="card hover:translate-y-0">
            <h3 class="text-xl font-extrabold text-slate-950 dark:text-white">n8n setup</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60"><p class="font-extrabold">1. HTTP Request</p><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Use the HTTP Request node and point it to an endpoint under <code>{{ $baseUrl }}</code>.</p></div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60"><p class="font-extrabold">2. Authentication</p><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Use Header Auth or add <code>Authorization: Bearer ...</code>. Keep the key in n8n credentials, not directly in workflow JSON.</p></div>
                <div class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950/60"><p class="font-extrabold">3. JSON body</p><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">For POST/PATCH requests enable JSON body and send only fields required by that resource.</p></div>
            </div>
        </section>

        <section class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
            <h3 class="font-extrabold">Security notes</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li>Never expose an API key in frontend JavaScript, public repositories, screenshots, or support tickets.</li>
                <li>Use the smallest permission set required by the integration.</li>
                <li>Use Allowed IPs where the calling server has a stable public IP.</li>
                <li>Revoke and replace a key immediately if it may have been exposed.</li>
            </ul>
        </section>
    </div>
</x-app-layout>
