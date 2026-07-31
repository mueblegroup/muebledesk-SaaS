<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">System Guide</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Installation, migration, MyInvois setup, daily use, maintenance and troubleshooting.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6" x-data="{ section: 'overview' }">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">App environment</p>
                <p class="mt-2 text-lg font-extrabold text-slate-950 dark:text-white">{{ strtoupper(app()->environment()) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">MyInvois</p>
                <p class="mt-2 text-lg font-extrabold {{ $myInvoisEnabled ? 'text-emerald-600' : 'text-amber-600' }}">{{ $myInvoisEnabled ? 'ENABLED' : 'DISABLED' }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">MyInvois environment</p>
                <p class="mt-2 text-lg font-extrabold {{ $environment === 'production' ? 'text-red-600' : 'text-amber-600' }}">{{ strtoupper($environment) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Queue</p>
                <p class="mt-2 text-lg font-extrabold {{ $queueConnection === 'sync' ? 'text-red-600' : 'text-emerald-600' }}">{{ strtoupper($queueConnection) }}</p>
            </div>
        </div>

        @if ($queueConnection === 'sync')
            <div class="rounded-3xl border border-red-200 bg-red-50 p-5 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                <h3 class="font-extrabold">Production warning</h3>
                <p class="mt-1 text-sm">The queue is using <code>sync</code>. Automated MyInvois polling and customer notifications should use Redis or the database queue with a continuously running worker.</p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
            <aside class="h-fit rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:sticky lg:top-24">
                <p class="px-3 pb-3 text-xs font-extrabold uppercase tracking-wider text-slate-400">Guide sections</p>
                @foreach([
                    'overview' => 'Overview',
                    'customers' => 'Customer guide',
                    'staff' => 'Staff guide',
                    'einvoice' => 'e-Invoice workflow',
                    'troubleshooting' => 'Troubleshooting',
                    'installation' => 'Installation & migration',
                    'production' => 'Production checklist',
                    'maintenance' => 'Updates & backups',
                ] as $key => $label)
                    @if (!in_array($key, ['installation', 'production', 'maintenance'], true) || $user?->isAdmin())
                        <button type="button" @click="section = '{{ $key }}'" :class="section === '{{ $key }}' ? 'bg-indigo-600 text-white' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'" class="mb-1 block w-full rounded-2xl px-3 py-2.5 text-left text-sm font-bold transition">{{ $label }}</button>
                    @endif
                @endforeach
            </aside>

            <div class="space-y-6">
                <section x-show="section === 'overview'" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-2xl font-extrabold text-slate-950 dark:text-white">Mueble Desk at a glance</h3>
                    <p class="mt-3 text-slate-600 dark:text-slate-300">Mueble Desk manages clients, quotations, invoices, payments, recurring invoices, expenses, customer portal access and Malaysian MyInvois e-Invoices.</p>
                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-5 dark:bg-slate-800"><h4 class="font-extrabold">Staff</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Create clients and documents, record payments, review e-Invoice status, correct invalid submissions and cancel valid documents within the permitted window.</p></div>
                        <div class="rounded-2xl bg-slate-50 p-5 dark:bg-slate-800"><h4 class="font-extrabold">Customers</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Maintain their own e-Invoice identity, view invoices, submit fully paid invoices to MyInvois, track validation and access the final QR.</p></div>
                    </div>
                </section>

                <section x-show="section === 'customers'" x-cloak class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-2xl font-extrabold">Customer guide</h3>
                    <ol class="mt-5 list-decimal space-y-3 pl-6 text-slate-700 dark:text-slate-300">
                        <li>Open <strong>My e-Invoice Profile</strong>.</li>
                        <li>Enter your NRIC and select <strong>Search NRIC in MyInvois</strong>.</li>
                        <li>Confirm the retrieved TIN, then enter your full legal name and billing address.</li>
                        <li>Save the profile.</li>
                        <li>Open a fully paid invoice from <strong>My Invoices</strong>.</li>
                        <li>Select <strong>Generate My e-Invoice</strong>, review the details and submit.</li>
                        <li>Wait for automatic processing. Refresh manually only when necessary.</li>
                        <li>When status is <strong>VALID</strong>, open the QR or validated MyInvois document.</li>
                    </ol>
                    <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Profile updates affect future submissions. They do not rewrite a previously submitted or valid e-Invoice.</div>
                </section>

                <section x-show="section === 'staff'" x-cloak class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-2xl font-extrabold">Staff guide</h3>
                    <div class="mt-5 space-y-5 text-slate-700 dark:text-slate-300">
                        <div><h4 class="font-extrabold">Client onboarding</h4><p class="mt-1 text-sm">Create the client, assign portal access, verify TIN/ID details and ensure the legal billing address is complete.</p></div>
                        <div><h4 class="font-extrabold">Sales flow</h4><p class="mt-1 text-sm">Create a quotation, convert it to an invoice, record payments and submit the e-Invoice only after the invoice is fully paid.</p></div>
                        <div><h4 class="font-extrabold">Invalid submissions</h4><p class="mt-1 text-sm">Read the exact MyInvois validation error, correct the source client or invoice data, and retry only when the status is INVALID, REJECTED or FAILED.</p></div>
                        <div><h4 class="font-extrabold">Cancellation</h4><p class="mt-1 text-sm">Only staff can cancel. Provide a clear reason and complete the action within the displayed cancellation window.</p></div>
                    </div>
                </section>

                <section x-show="section === 'einvoice'" x-cloak class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-2xl font-extrabold">e-Invoice lifecycle</h3>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach(['READY' => 'Profile and invoice checks pass', 'SUBMITTED' => 'Accepted for processing', 'VALID' => 'QR and final link available', 'INVALID' => 'Correct errors and retry'] as $status => $description)
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><p class="font-extrabold">{{ $status }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p></div>
                        @endforeach
                    </div>
                    <div class="mt-6 space-y-3 text-sm text-slate-700 dark:text-slate-300">
                        <p><strong>Do not blindly retry:</strong> timeouts, 429 responses, duplicate submissions and uncertain network outcomes may require reconciliation first.</p>
                        <p><strong>Production:</strong> requires both the general MyInvois switch and the separate Production switch, plus the typed confirmation shown on the submission page.</p>
                        <p><strong>Notifications:</strong> a running queue worker is required for automatic status polling and customer email notifications.</p>
                    </div>
                </section>

                <section x-show="section === 'troubleshooting'" x-cloak class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-2xl font-extrabold">Troubleshooting</h3>
                    <div class="mt-5 space-y-4">
                        @foreach([
                            'HTTP 500' => 'Check storage/logs/laravel.log, database connectivity, APP_KEY, PHP extensions, permissions and cached configuration.',
                            'Queue not processing' => 'Check QUEUE_CONNECTION, Redis, Supervisor status, failed jobs and queue-worker.log.',
                            'Email not arriving' => 'Verify SMTP settings, the queue worker, sender DNS and the spam folder.',
                            'MyInvois lookup fails' => 'Check the selected environment, API credentials, TLS, server clock and the TIN/ID combination.',
                            'Missing assets' => 'Run npm ci, npm run build and clear Laravel caches.',
                        ] as $title => $text)
                            <div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><h4 class="font-extrabold">{{ $title }}</h4><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $text }}</p></div>
                        @endforeach
                    </div>
                </section>

                @if ($user?->isAdmin())
                    <section x-show="section === 'installation'" x-cloak class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-2xl font-extrabold">Installation and server migration</h3>
                        <p class="mt-3 text-slate-600 dark:text-slate-300">Use the existing Git repository. Copy the database, `.env`, and uploaded files—but reinstall dependencies and rebuild caches on the new server.</p>
                        <h4 class="mt-6 font-extrabold">Install</h4>
                        <pre class="mt-2 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300">git clone https://github.com/mueblegroup/muebledesk.git /var/www/muebledesk
cd /var/www/muebledesk
git checkout agent/myinvois-phase-1
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link</pre>
                        <h4 class="mt-6 font-extrabold">Copy from the old server</h4>
                        <ul class="mt-2 list-disc space-y-2 pl-6 text-sm text-slate-600 dark:text-slate-300"><li>Database dump</li><li>Existing `.env` with the same `APP_KEY`</li><li>User uploads under `storage/app`</li><li>Any uncommitted custom public assets</li></ul>
                        <p class="mt-4 text-sm font-semibold text-red-600">Do not copy vendor, node_modules, framework caches, sessions, logs or temporary queue data.</p>
                    </section>

                    <section x-show="section === 'production'" x-cloak class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-2xl font-extrabold">Production checklist</h3>
                        <div class="mt-5 grid gap-3 md:grid-cols-2">
                            @foreach(['APP_ENV=production and APP_DEBUG=false','HTTPS with public web root set to /public','Database migrated and backed up','Redis and Supervisor worker running','SMTP tested through the queue','Cron runs schedule:run every minute','Sandbox lifecycle completed successfully','Production credentials configured separately','Production remains disabled until final review','Monitoring and off-server backups enabled'] as $item)
                                <label class="flex gap-3 rounded-2xl border border-slate-200 p-4 text-sm dark:border-slate-700"><input type="checkbox" class="mt-1 rounded"><span>{{ $item }}</span></label>
                            @endforeach
                        </div>
                    </section>

                    <section x-show="section === 'maintenance'" x-cloak class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-2xl font-extrabold">Updates and backups</h3>
                        <pre class="mt-5 overflow-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300">php artisan down --retry=60
git pull --ff-only origin agent/myinvois-phase-1
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up</pre>
                        <p class="mt-5 text-sm text-slate-600 dark:text-slate-300">Back up the database, `storage/app`, and an encrypted copy of `.env`. Keep an off-server copy and test restoration regularly.</p>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
