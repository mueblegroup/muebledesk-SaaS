@props([
    'context' => 'document',
    'formId' => 'quick-client-form',
])

<div x-data="{ openQuickClient: {{ $errors->quickClient->any() ? 'true' : 'false' }} }" class="mt-3">
    <button type="button" @click="openQuickClient = !openQuickClient" class="text-sm font-bold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
        + Add new client here
    </button>

    <div x-show="openQuickClient" x-cloak class="mt-4 rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/70">
        <div class="mb-4">
            <h4 class="text-sm font-extrabold text-slate-950 dark:text-white">Quick add client</h4>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Add the required basics now for this {{ $context }}. Address, TIN, and other details can be completed later from the client profile.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <label for="quick_client_name_{{ $context }}" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Client Name <span class="ml-1 text-xs font-bold text-red-600">Required</span></label>
                <input id="quick_client_name_{{ $context }}" name="name" type="text" value="{{ old('name') }}" class="block w-full" form="{{ $formId }}" required>
                @error('name', 'quickClient')
                    <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="quick_client_email_{{ $context }}" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Email <span class="ml-1 text-xs font-bold text-red-600">Required</span></label>
                <input id="quick_client_email_{{ $context }}" name="email" type="email" value="{{ old('email') }}" class="block w-full" form="{{ $formId }}" required>
                @error('email', 'quickClient')
                    <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="quick_client_phone_{{ $context }}" class="mb-2 block text-sm font-bold text-slate-700 dark:text-slate-200">Phone Optional</label>
                <input id="quick_client_phone_{{ $context }}" name="phone" type="text" value="{{ old('phone') }}" class="block w-full" form="{{ $formId }}">
                @error('phone', 'quickClient')
                    <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <label class="md:col-span-3 flex gap-3 rounded-2xl border border-indigo-100 bg-white p-3 text-sm text-slate-700 dark:border-indigo-900 dark:bg-slate-950 dark:text-slate-200">
                <input type="checkbox" name="send_password_setup_link" value="1" form="{{ $formId }}" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block font-extrabold text-slate-950 dark:text-white">Email password setup link now</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">The customer can set their own password securely through the reset-password link.</span>
                </span>
            </label>

            <div class="md:col-span-3 flex flex-wrap items-center justify-end gap-3">
                <button type="button" @click="openQuickClient = false" class="btn-secondary">Cancel</button>
                <button type="submit" form="{{ $formId }}" class="btn-primary">Add Client</button>
            </div>
        </div>
    </div>
</div>