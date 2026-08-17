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
            'action' => ['required', Rule::in(['change_plan', 'activate', 'extend', 'disable', 'expire'])],
            'auto_renew' => ['nullable', 'boolean'],
        ]);

        $plan = PlatformSubscriptionPlan::findOrFail($validated['plan_id']);
        $subscription = $company->subscription()->firstOrCreate([], [
            'platform_subscription_plan_id' => $plan->id,
            'status' => 'inactive',
            'auto_renew' => $plan->auto_renew_default,
            'is_enabled' => true,
        ]);

        $previousPlanId = $subscription->platform_subscription_plan_id;

        $subscription->update([
            'platform_subscription_plan_id' => $plan->id,
            'auto_renew' => $request->boolean('auto_renew', $subscription->auto_renew),
        ]);
        $subscription->load('plan');

        match ($validated['action']) {
            'change_plan' => null,
            'activate' => $subscription->activate(),
            'extend' => $subscription->extend(),
            'disable' => $subscription->update(['is_enabled' => false, 'status' => 'disabled']),
            'expire' => $subscription->update(['expires_at' => now(), 'status' => 'expired']),
        };

        if ($validated['action'] === 'change_plan') {
            $message = $previousPlanId === $plan->id
                ? 'Company subscription settings updated.'
                : "Company plan changed to {$plan->name}. Existing subscription dates and status were preserved.";

            return back()->with('success', $message);
        }

        return back()->with('success', 'Company subscription updated.');
    }
}
