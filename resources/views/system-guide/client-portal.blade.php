<x-client-portal-layout>
    <x-slot name="title">System Guide</x-slot>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white sm:text-2xl">System Guide</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Client portal onboarding, subscription access and workspace usage.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Step 1</p><h2 class="mt-2 text-lg font-extrabold">Create your company</h2><p class="mt-2 text-sm leading-6 text-slate-500">Enter the company details once to create its isolated IMS workspace and subdomain.</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Step 2</p><h2 class="mt-2 text-lg font-extrabold">Choose a plan</h2><p class="mt-2 text-sm leading-6 text-slate-500">Select the required duration and admin, employee and client limits from Plans & Billing.</p></div>
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Step 3</p><h2 class="mt-2 text-lg font-extrabold">Open the workspace</h2><p class="mt-2 text-sm leading-6 text-slate-500">Use Overview to launch the company IMS after the subscription becomes active.</p></div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            <h2 class="text-2xl font-extrabold">Client portal navigation</h2>
            <div class="mt-6 grid gap-4 md:grid-cols-2">
                @foreach ([
                    'Overview' => 'Review company status, role usage, expiry date and workspace access.',
                    'Create company' => 'Create the initial company workspace. Once a company exists, this page redirects safely to Overview.',
                    'System guide' => 'Review onboarding, billing and workspace instructions.',
                    'Profile & security' => 'Manage your name, email, password and two-factor authentication.',
                    'Plans & billing' => 'Purchase, renew and manage the company subscription and automatic renewal setting.',
                ] as $title => $description)
                    <div class="rounded-2xl bg-slate-50 p-5 dark:bg-slate-800"><h3 class="font-extrabold">{{ $title }}</h3><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $description }}</p></div>
                @endforeach
            </div>
        </section>

        <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6 text-indigo-950 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-100">
            <h2 class="text-lg font-extrabold">Subscription access</h2>
            <p class="mt-2 text-sm leading-6">When a subscription expires or is disabled, company data remains intact but the IMS workspace is blocked until the plan is renewed or reactivated.</p>
        </section>
    </div>
</x-client-portal-layout>
