<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('System Settings') }}
        </h2>
    </x-slot>

    <div class="space-y-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h3 class="section-title">Admin Settings</h3>
                <p class="section-subtitle">Configure company details, invoices, payment gateways, email defaults, and system behaviour.</p>
            </div>
            <div class="space-y-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                <div>HitPay webhook: <span class="font-semibold text-slate-950 dark:text-white">{{ $hitpayWebhookUrl ?? $webhookUrl }}</span></div>
                <div>Stripe webhook: <span class="font-semibold text-slate-950 dark:text-white">{{ $stripeWebhookUrl ?? route('stripe.webhook') }}</span></div>
            </div>
        </div>

        <div class="rounded-3xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 class="font-extrabold text-indigo-950 dark:text-indigo-100">MyInvois e-Invoice</h4>
                    <p class="mt-1 text-sm text-indigo-700 dark:text-indigo-300">Configure this company’s encrypted ERP credentials, supplier taxpayer profile, environment, and connection test in the dedicated setup screen.</p>
                </div>
                <a href="{{ route('admin.einvoice-settings.index') }}" class="btn-secondary whitespace-nowrap">Open e-Invoice Setup</a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                <p class="font-bold">Please fix the following:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.setting.update') }}" enctype="multipart/form-data" class="space-y-8">
            @csrf
            @method('PUT')

            @foreach ($sections as $sectionKey => $section)
                @continue($sectionKey === 'myinvois')
                <section class="space-y-5 border-t border-slate-200 pt-8 dark:border-slate-800">
                    <div>
                        <h4 class="text-lg font-extrabold text-slate-950 dark:text-white">{{ $section['title'] }}</h4>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $section['description'] }}</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($section['fields'] as $key => $field)
                            @php
                                $type = $field['type'] ?? 'text';
                                $value = old($key, $settings[$key] ?? ($field['default'] ?? ''));
                                $hasStoredSecret = $type === 'password_text' && ! empty($settings[$key] ?? null);
                            @endphp

                            <div class="{{ in_array($type, ['textarea'], true) ? 'md:col-span-2 xl:col-span-3' : '' }}">
                                <label for="{{ $key }}" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">
                                    {{ $field['label'] }}
                                </label>

                                @if ($type === 'textarea')
                                    <textarea id="{{ $key }}" name="{{ $key }}" rows="4" class="block w-full">{{ $value }}</textarea>
                                @elseif ($type === 'select')
                                    <select id="{{ $key }}" name="{{ $key }}" class="block w-full">
                                        @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($type === 'password_text')
                                    <input id="{{ $key }}" name="{{ $key }}" type="password" value="" placeholder="{{ $hasStoredSecret ? 'Configured — leave blank to keep existing value' : 'Not configured' }}" autocomplete="new-password" class="block w-full font-mono text-sm">
                                    @if ($hasStoredSecret)
                                        <p class="mt-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">Secret is configured and encrypted. Enter a new value only if you want to replace it.</p>
                                    @endif
                                @elseif ($type === 'file')
                                    @if ($value)
                                        <img src="{{ asset('storage/'.$value) }}" alt="Current company logo" class="mb-3 max-h-20 max-w-xs rounded-xl border border-slate-200 bg-white p-2">
                                    @endif
                                    <input id="{{ $key }}" name="{{ $key }}" type="file" accept="image/png,image/jpeg" class="block w-full">
                                @else
                                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $type }}" value="{{ $value }}" class="block w-full">
                                @endif

                                @error($key)
                                    <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror

                                @if (! empty($field['help']))
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $field['help'] }}</p>
                                @endif

                                @if ($key === 'hitpay_webhook_url')
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        Set this in HitPay dashboard. Default route: <span class="font-semibold">{{ $hitpayWebhookUrl ?? $webhookUrl }}</span>
                                    </p>
                                @endif

                                @if ($key === 'stripe_webhook_url')
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        Set this in Stripe Dashboard → Developers → Webhooks. Default route: <span class="font-semibold">{{ $stripeWebhookUrl ?? route('stripe.webhook') }}</span>
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="sticky bottom-0 -mx-4 border-t border-slate-200 bg-white/90 px-4 py-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500 dark:text-slate-400">Changes are saved into the company-scoped settings table.</p>
                    <button type="submit" class="btn-primary">
                        {{ __('Save Settings') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
