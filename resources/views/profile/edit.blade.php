@php
    $layoutComponent = request()->getHost() === config('saas.central_domain')
        ? 'client-portal-layout'
        : 'app-layout';
@endphp

<x-dynamic-component :component="$layoutComponent">
    <x-slot name="title">Profile & Security</x-slot>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Profile & Security</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage your account details, password and two-factor authentication.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            <div class="max-w-xl">@include('profile.partials.update-profile-information-form')</div>
        </div>

        @if ($user->isCustomer())
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
                <div class="max-w-5xl">@include('profile.partials.update-business-details-form')</div>
            </div>
        @endif

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            <div class="max-w-3xl">@include('profile.partials.two-factor-authentication-form')</div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            <div class="max-w-xl">@include('profile.partials.update-password-form')</div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-8">
            <div class="max-w-xl">@include('profile.partials.delete-user-form')</div>
        </div>
    </div>
</x-dynamic-component>
