<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CompanySubscription;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function payments(Request $request): View
    {
        $query = SubscriptionPayment::query()->with(['company','plan','subscription']);
        if ($status = $request->query('status')) $query->where('status',$status);
        if ($search = trim((string)$request->query('search'))) {
            $query->where(function($q) use($search){
                $q->where('provider_invoice_id','like',"%{$search}%")
                    ->orWhere('provider_payment_id','like',"%{$search}%")
                    ->orWhereHas('company',fn($c)=>$c->where('name','like',"%{$search}%"));
            });
        }

        return view('superadmin.payments.index',[
            'payments'=>$query->latest()->paginate(30)->withQueryString(),
            'totals'=>[
                'paid'=>(float)SubscriptionPayment::where('status','paid')->sum('amount'),
                'failed'=>SubscriptionPayment::where('status','failed')->count(),
                'active'=>CompanySubscription::whereIn('status',['active','trialing'])->count(),
                'past_due'=>CompanySubscription::where('status','past_due')->count(),
            ],
        ]);
    }

    public function settings(): View
    {
        $get = fn(string $key,mixed $default=null)=>PlatformSetting::valueFor($key,$default);
        $googleReady = filled(config('services.google.client_id')) && filled(config('services.google.client_secret')) && filled(config('services.google.redirect'));

        return view('superadmin.settings.index',[
            'settings'=>[
                'platform_name'=>$get('platform.name',config('app.name')),
                'support_email'=>$get('platform.support_email'),
                'support_phone'=>$get('platform.support_phone'),
                'default_currency'=>$get('billing.default_currency','MYR'),
                'grace_period_days'=>$get('billing.grace_period_days','3'),
                'allow_registration'=>$get('auth.allow_registration','1'),
                'require_2fa_superadmin'=>$get('auth.require_2fa_superadmin','1'),
                'require_2fa_company_admin'=>$get('auth.require_2fa_company_admin','0'),
                'maintenance_banner'=>$get('platform.maintenance_banner'),
                'privacy_url'=>$get('platform.privacy_url'),
                'terms_url'=>$get('platform.terms_url'),
            ],
            'status'=>[
                'stripe'=>filled(config('services.stripe.secret')),
                'stripe_webhook'=>filled(config('services.stripe.platform_webhook_secret')),
                'mail'=>filled(config('mail.mailers.smtp.host')),
                'google'=>filled(config('services.google.client_id')),
                'microsoft'=>filled(config('services.microsoft.client_id')),
                'apple'=>filled(config('services.apple.client_id')),
                'socialite'=>class_exists(\Laravel\Socialite\Facades\Socialite::class),
                'settings_table'=>Schema::hasTable('platform_settings'),
            ],
            'secretStatus'=>[
                'google_client_secret'=>filled($get('sso.google_client_secret',config('services.google.client_secret'))),
                'microsoft_client_secret'=>filled($get('sso.microsoft_client_secret',config('services.microsoft.client_secret'))),
                'apple_client_secret'=>filled($get('sso.apple_client_secret',config('services.apple.client_secret'))),
            ],
            'sso'=>[
                'google_enabled'=>$get('sso.google_enabled',$googleReady?'1':'0'),
                'google_client_id'=>$get('sso.google_client_id',config('services.google.client_id')),
                'google_redirect'=>$get('sso.google_redirect',config('services.google.redirect')),
                'microsoft_enabled'=>$get('sso.microsoft_enabled','0'),
                'microsoft_client_id'=>$get('sso.microsoft_client_id',config('services.microsoft.client_id')),
                'microsoft_tenant'=>$get('sso.microsoft_tenant',config('services.microsoft.tenant','common')),
                'microsoft_redirect'=>$get('sso.microsoft_redirect',config('services.microsoft.redirect')),
                'apple_enabled'=>$get('sso.apple_enabled','0'),
                'apple_client_id'=>$get('sso.apple_client_id',config('services.apple.client_id')),
                'apple_redirect'=>$get('sso.apple_redirect',config('services.apple.redirect')),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated=$request->validate([
            'platform_name'=>['required','string','max:100'],'support_email'=>['nullable','email','max:255'],
            'support_phone'=>['nullable','string','max:50'],'default_currency'=>['required','string','size:3'],
            'grace_period_days'=>['required','integer','min:0','max:90'],'maintenance_banner'=>['nullable','string','max:500'],
            'privacy_url'=>['nullable','url','max:255'],'terms_url'=>['nullable','url','max:255'],
            'google_client_id'=>['nullable','string','max:255'],'google_client_secret'=>['nullable','string','max:1000'],
            'google_redirect'=>['nullable','url','max:255'],'microsoft_client_id'=>['nullable','string','max:255'],
            'microsoft_client_secret'=>['nullable','string','max:1000'],'microsoft_tenant'=>['nullable','string','max:255'],
            'microsoft_redirect'=>['nullable','url','max:255'],'apple_client_id'=>['nullable','string','max:255'],
            'apple_client_secret'=>['nullable','string','max:3000'],'apple_redirect'=>['nullable','url','max:255'],
        ]);

        $plain=[
            'platform.name'=>$validated['platform_name'],'platform.support_email'=>$validated['support_email']??null,
            'platform.support_phone'=>$validated['support_phone']??null,'billing.default_currency'=>strtoupper($validated['default_currency']),
            'billing.grace_period_days'=>(string)$validated['grace_period_days'],'platform.maintenance_banner'=>$validated['maintenance_banner']??null,
            'platform.privacy_url'=>$validated['privacy_url']??null,'platform.terms_url'=>$validated['terms_url']??null,
            'auth.allow_registration'=>$request->boolean('allow_registration')?'1':'0',
            'auth.require_2fa_superadmin'=>$request->boolean('require_2fa_superadmin')?'1':'0',
            'auth.require_2fa_company_admin'=>$request->boolean('require_2fa_company_admin')?'1':'0',
            'sso.google_enabled'=>$request->boolean('google_enabled')?'1':'0',
            'sso.google_client_id'=>$validated['google_client_id']??null,'sso.google_redirect'=>$validated['google_redirect']??null,
            'sso.microsoft_enabled'=>$request->boolean('microsoft_enabled')?'1':'0',
            'sso.microsoft_client_id'=>$validated['microsoft_client_id']??null,'sso.microsoft_tenant'=>$validated['microsoft_tenant']??'common',
            'sso.microsoft_redirect'=>$validated['microsoft_redirect']??null,
            'sso.apple_enabled'=>$request->boolean('apple_enabled')?'1':'0',
            'sso.apple_client_id'=>$validated['apple_client_id']??null,
            'sso.apple_redirect'=>$validated['apple_redirect']??null,
        ];
        foreach($plain as $key=>$value) PlatformSetting::put(str($key)->before('.')->toString(),$key,$value,false);
        foreach(['google','microsoft','apple'] as $provider){
            $field=$provider.'_client_secret';
            if(filled($validated[$field]??null)) PlatformSetting::put('sso','sso.'.$field,$validated[$field],true);
        }

        return back()->with('success','Platform settings updated. Secret fields left blank were preserved.');
    }
}
