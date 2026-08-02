<section>
    <header>
        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Client portal identity</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">These details identify the authorised person managing company access and billing.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf @method('patch')
        <div><x-input-label for="name" value="Full legal name"/><x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name',$user->name)" required/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
        <div><x-input-label for="email" value="Work email"/><x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email',$user->email)" required/><x-input-error class="mt-2" :messages="$errors->get('email')"/></div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><x-input-label for="phone" value="Mobile number"/><x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone',$user->phone)" required/><x-input-error class="mt-2" :messages="$errors->get('phone')"/></div>
            <div><x-input-label for="job_title" value="Job title / role"/><x-text-input id="job_title" name="job_title" class="mt-1 block w-full" :value="old('job_title',$user->job_title)" required/><x-input-error class="mt-2" :messages="$errors->get('job_title')"/></div>
        </div>
        <div><x-input-label for="address" value="Residential or correspondence address"/><textarea id="address" name="address" rows="3" class="mt-1 block w-full rounded-2xl border-slate-300" required>{{ old('address',$user->address) }}</textarea><x-input-error class="mt-2" :messages="$errors->get('address')"/></div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><x-input-label for="country_code" value="Country code"/><x-text-input id="country_code" name="country_code" maxlength="2" class="mt-1 block w-full uppercase" :value="old('country_code',$user->country_code ?? 'MY')" required/></div>
            <div><x-input-label for="preferred_timezone" value="Timezone"/><x-text-input id="preferred_timezone" name="preferred_timezone" class="mt-1 block w-full" :value="old('preferred_timezone',$user->preferred_timezone ?? 'Asia/Kuala_Lumpur')" required/></div>
        </div>

        @if (! $user->hasVerifiedEmail())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Your email is not verified. <button form="send-verification" class="font-bold underline">Send another verification email</button>.</div>
        @endif

        <div class="flex items-center gap-4"><x-primary-button>Save identity details</x-primary-button>@if(session('status')==='profile-updated')<span class="text-sm text-emerald-600">Saved.</span>@endif</div>
    </form>
</section>
