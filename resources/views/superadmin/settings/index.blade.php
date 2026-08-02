<x-superadmin-layout>
    <x-slot name="title">Platform Settings</x-slot>
    <x-slot name="header"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-violet-600">Control plane</p><h1 class="mt-1 text-2xl font-black">Platform settings</h1></div></x-slot>

    <div class="space-y-7">
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(['Stripe'=>$status['stripe'],'Stripe webhook'=>$status['stripe_webhook'],'SMTP mail'=>$status['mail'],'OAuth packages'=>$status['socialite'],'Google SSO'=>$status['google'],'Microsoft SSO'=>$status['microsoft'],'Apple SSO'=>$status['apple'],'Settings database'=>$status['settings_table']] as $label=>$ok)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center justify-between gap-3"><span class="text-sm font-bold">{{ $label }}</span><span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $ok?'bg-emerald-50 text-emerald-700':'bg-amber-50 text-amber-700' }}">{{ $ok?'Ready':'Needs setup' }}</span></div></div>
            @endforeach
        </section>

        <form method="POST" action="{{ route('superadmin.settings.update') }}" class="space-y-7">@csrf @method('PUT')
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-black">General & support</h2><div class="mt-5 grid gap-4 md:grid-cols-2">
                <label class="text-sm font-bold">Platform name<input name="platform_name" value="{{ old('platform_name',$settings['platform_name']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950" required></label>
                <label class="text-sm font-bold">Default currency<input name="default_currency" maxlength="3" value="{{ old('default_currency',$settings['default_currency']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 uppercase dark:border-slate-700 dark:bg-slate-950" required></label>
                <label class="text-sm font-bold">Support email<input type="email" name="support_email" value="{{ old('support_email',$settings['support_email']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="text-sm font-bold">Support phone<input name="support_phone" value="{{ old('support_phone',$settings['support_phone']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="text-sm font-bold">Terms URL<input type="url" name="terms_url" value="{{ old('terms_url',$settings['terms_url']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="text-sm font-bold">Privacy URL<input type="url" name="privacy_url" value="{{ old('privacy_url',$settings['privacy_url']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>
            </div><label class="mt-4 block text-sm font-bold">Platform notice / maintenance banner<textarea name="maintenance_banner" rows="3" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950">{{ old('maintenance_banner',$settings['maintenance_banner']) }}</textarea></label></section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-black">Authentication & security</h2><div class="mt-5 grid gap-4 md:grid-cols-2">
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><input type="checkbox" name="allow_registration" value="1" @checked(old('allow_registration',$settings['allow_registration'])==='1') class="mt-1 rounded"><span><strong>Allow public registration</strong><small class="mt-1 block text-slate-500">Disable during maintenance or controlled launches.</small></span></label>
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><input type="checkbox" name="require_2fa_superadmin" value="1" @checked(old('require_2fa_superadmin',$settings['require_2fa_superadmin'])==='1') class="mt-1 rounded"><span><strong>Require 2FA for superadmins</strong><small class="mt-1 block text-slate-500">Superadmins must finish authenticator setup.</small></span></label>
                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><input type="checkbox" name="require_2fa_company_admin" value="1" @checked(old('require_2fa_company_admin',$settings['require_2fa_company_admin'])==='1') class="mt-1 rounded"><span><strong>Require 2FA for company admins</strong><small class="mt-1 block text-slate-500">Recommended for financial access.</small></span></label>
                <label class="text-sm font-bold">Failed-payment grace period (days)<input type="number" min="0" max="90" name="grace_period_days" value="{{ old('grace_period_days',$settings['grace_period_days']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>
            </div><div class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-950">Your own 2FA status: <strong>{{ auth()->user()->hasTwoFactorEnabled()?'Enabled':'Not enabled' }}</strong>. <a href="{{ route('profile.edit') }}" class="font-bold text-indigo-600">Open Profile & Security</a></div></section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-xl font-black">Single sign-on providers</h2><p class="mt-1 text-sm text-slate-500">Secrets are encrypted. Leave a secret blank to preserve the existing value.</p>
                @foreach(['google'=>'Google','microsoft'=>'Microsoft','apple'=>'Apple'] as $provider=>$label)<div class="mt-5 rounded-2xl border border-slate-200 p-5 dark:border-slate-700"><h3 class="font-black">{{ $label }}</h3><div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-bold">Client ID<input name="{{ $provider }}_client_id" value="{{ old($provider.'_client_id',$sso[$provider.'_client_id']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>
                    <label class="text-sm font-bold">Client secret <span class="text-xs text-slate-400">({{ $secretStatus[$provider.'_client_secret']?'configured':'not configured' }})</span><input type="password" name="{{ $provider }}_client_secret" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950" autocomplete="new-password"></label>
                    @if($provider==='microsoft')<label class="text-sm font-bold">Tenant<input name="microsoft_tenant" value="{{ old('microsoft_tenant',$sso['microsoft_tenant']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>@endif
                    <label class="text-sm font-bold">Callback URL<input type="url" name="{{ $provider }}_redirect" value="{{ old($provider.'_redirect',$sso[$provider.'_redirect']) }}" class="mt-2 block w-full rounded-2xl border-slate-200 dark:border-slate-700 dark:bg-slate-950"></label>
                </div></div>@endforeach
            </section>

            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><strong>Operational settings still remain in the server environment:</strong> APP_KEY, database credentials, queue/Redis credentials, Stripe secret keys, webhook signing secrets and SMTP password. They are intentionally not exposed for editing in the browser.</section>
            <div class="flex justify-end"><button class="rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white">Save platform settings</button></div>
        </form>
    </div>
</x-superadmin-layout>
