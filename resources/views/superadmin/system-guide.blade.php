<x-superadmin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-950 dark:text-white">System Guide</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Platform operations, production checks, infrastructure and MyInvois administration.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Environment</p><p class="mt-2 text-lg font-extrabold">{{ strtoupper(app()->environment()) }}</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">MyInvois master</p><p class="mt-2 text-lg font-extrabold {{ $myInvoisEnabled ? 'text-emerald-600' : 'text-amber-600' }}">{{ $myInvoisEnabled ? 'ENABLED' : 'DISABLED' }}</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Production submissions</p><p class="mt-2 text-lg font-extrabold {{ $productionEnabled ? 'text-red-600' : 'text-emerald-600' }}">{{ $productionEnabled ? 'ENABLED' : 'LOCKED' }}</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Queue</p><p class="mt-2 text-lg font-extrabold {{ $queueConnection === 'sync' ? 'text-red-600' : 'text-emerald-600' }}">{{ strtoupper($queueConnection) }}</p></div>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-extrabold">Production deployment</h2>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-slate-950 p-4 text-xs text-emerald-300"><code>git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan app:production-check --strict</code></pre>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-extrabold">Required services</h2>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
                    <li>HTTPS with the public web root pointing to Laravel's <code>/public</code>.</li>
                    <li>Persistent queue driver with supervised workers.</li>
                    <li>Scheduler executing <code>php artisan schedule:run</code> every minute.</li>
                    <li>Production SMTP transport and verified sender identity.</li>
                    <li>Database and uploaded-file backups stored off-server.</li>
                    <li>Wildcard DNS/TLS covering company subdomains.</li>
                    <li>Payment gateway webhook endpoints registered and monitored.</li>
                </ul>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-extrabold">SaaS MyInvois model</h2>
                <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-600 dark:text-slate-300">
                    <li>Platform <code>.env</code> controls MyInvois transport URLs and production safety switches.</li>
                    <li>Each tenant stores its own encrypted ERP Client ID and Client Secret.</li>
                    <li>Supplier identity and TIN are tenant-specific and must never fall back across companies.</li>
                    <li>Access tokens are cached separately by company and environment.</li>
                    <li>Queued polling jobs restore the e-Invoice company context before calling MyInvois.</li>
                </ul>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-xl font-extrabold">Operational checklist</h2>
            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    'APP_ENV=production and APP_DEBUG=false',
                    'Production database migrated',
                    'Queue worker supervised and healthy',
                    'Scheduler running every minute',
                    'Redis/cache connectivity verified',
                    'SMTP delivery tested',
                    'Stripe/HitPay webhooks verified',
                    'Tenant isolation smoke-tested',
                    'MyInvois sandbox tested per tenant',
                    'Backup restore test completed',
                    'HTTPS and wildcard subdomains verified',
                    'Production readiness check passes',
                ] as $item)
                    <div class="flex gap-3 rounded-2xl border border-slate-200 p-4 text-sm dark:border-slate-700"><span class="mt-0.5 text-emerald-500">✓</span><span>{{ $item }}</span></div>
                @endforeach
            </div>
        </section>
    </div>
</x-superadmin-layout>
