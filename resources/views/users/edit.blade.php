<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Edit User</h2>
    </x-slot>

    <div class="space-y-6">
        <div>
            <h3 class="section-title">{{ $user->name }}</h3>
            <p class="section-subtitle">Update account, contact, role, billing, and tax details.</p>
        </div>

        <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            @method('PUT')
            @include('users._form', ['user' => $user])
            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end dark:border-slate-800">
                <a href="{{ route('users.index') }}" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Update User</button>
            </div>
        </form>
    </div>
</x-app-layout>
