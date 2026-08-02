<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformSubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanySubscriptionController extends Controller
{
    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', Rule::exists('platform_subscription_plans', 'id')],
            'action' => ['required', Rule::in(['activate', 'extend', 'disable', 'expire'])],
            'auto_renew' => ['nullable', 'boolean'],
        ]);

        $plan = PlatformSubscriptionPlan::findOrFail($validated['plan_id']);
        $subscription = $company->subscription()->firstOrCreate([], [
            'platform_subscription_plan_id' => $plan->id,
            'status' => 'inactive',
            'auto_renew' => $plan->auto_renew_default,
            'is_enabled' => true,
        ]);

        $subscription->update([
            'platform_subscription_plan_id' => $plan->id,
            'auto_renew' => $request->boolean('auto_renew', $subscription->auto_renew),
        ]);
        $subscription->load('plan');

        match ($validated['action']) {
            'activate' => $subscription->activate(),
            'extend' => $subscription->extend(),
            'disable' => $subscription->update(['is_enabled' => false, 'status' => 'disabled']),
            'expire' => $subscription->update(['expires_at' => now(), 'status' => 'expired']),
        };

        return back()->with('success', 'Company subscription updated.');
    }
}
