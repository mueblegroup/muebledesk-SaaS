<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="space-y-4 text-slate-700 dark:text-slate-300">
        <p class="text-lg font-semibold text-slate-950 dark:text-white">{{ __("You're logged in!") }}</p>

        @if(Auth::user()->isAdmin())
            <p>Welcome, Admin!</p>
            <p>Access your <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>.</p>
        @elseif(Auth::user()->isEmployee())
            <p>Welcome, Employee!</p>
            <p>Access your <a href="{{ route('employee.dashboard') }}">Employee Dashboard</a>.</p>
        @elseif(Auth::user()->isCustomer())
            <p>Welcome, Customer!</p>
            <p>Access your <a href="{{ route('customer.dashboard') }}">Customer Portal</a>.</p>
        @endif
    </div>
</x-app-layout>
