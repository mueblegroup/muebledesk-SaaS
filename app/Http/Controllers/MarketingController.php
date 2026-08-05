<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\PlatformSubscriptionPlan;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function home(): View
    {
        return view('marketing.home');
    }

    public function features(): View
    {
        return view('marketing.features');
    }

    public function howItWorks(): View
    {
        return view('marketing.how-it-works');
    }

    public function security(): View
    {
        return view('marketing.security');
    }

    public function pricing(): View
    {
        $plans = Schema::hasTable('platform_subscription_plans')
            ? PlatformSubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->get()
            : collect();

        return view('marketing.pricing', compact('plans'));
    }

    public function contact(): View
    {
        $supportEmail = config('mail.from.address', 'support@example.com');

        if (Schema::hasTable('platform_settings')) {
            $supportEmail = PlatformSetting::valueFor('general.support_email', $supportEmail);
        }

        return view('marketing.contact', compact('supportEmail'));
    }
}
