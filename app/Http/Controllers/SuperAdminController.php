<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    public function dashboard(): View
    {
        return view('superadmin.dashboard', [
            'companyCount' => Company::count(),
            'userCount' => User::count(),
            'activeSubscriptionCount' => CompanySubscription::whereIn('status', ['active', 'trialing'])->count(),
            'monthlyRecurringRevenue' => CompanySubscription::query()
                ->whereIn('status', ['active', 'trialing'])
                ->with('plan')
                ->get()
                ->sum(fn ($subscription) => (float) ($subscription->plan?->price_per_seat ?? 0) * $subscription->seats),
            'companies' => Company::with(['owners', 'subscription.plan'])->latest()->take(10)->get(),
        ]);
    }

    public function users(): View
    {
        return view('superadmin.users.index', [
            'superadmins' => User::where('role', UserRoleEnum::SuperAdmin->value)->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => UserRoleEnum::SuperAdmin,
            'email_verified_at' => now(),
            'current_company_id' => null,
        ]);

        return back()->with('success', 'Superadmin account created.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isSuperAdmin(), 404);
        abort_if($request->user()->is($user), 422, 'You cannot delete your own superadmin account.');
        abort_if(User::where('role', UserRoleEnum::SuperAdmin->value)->count() <= 1, 422, 'The last superadmin cannot be deleted.');

        $user->delete();

        return back()->with('success', 'Superadmin account deleted.');
    }

    public function plans(): View
    {
        return view('superadmin.plans.index', [
            'plans' => PlatformSubscriptionPlan::orderBy('sort_order')->orderBy('price_per_seat')->get(),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);
        PlatformSubscriptionPlan::create($validated + ['slug' => Str::slug($validated['name'])]);

        return back()->with('success', 'Plan created.');
    }

    public function updatePlan(Request $request, PlatformSubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $plan);
        $plan->update($validated + ['slug' => Str::slug($validated['name'])]);

        return back()->with('success', 'Plan updated.');
    }

    public function destroyPlan(PlatformSubscriptionPlan $plan): RedirectResponse
    {
        abort_if($plan->subscriptions()->exists(), 422, 'Plans with subscriptions cannot be deleted.');
        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }

    private function validatePlan(Request $request, ?PlatformSubscriptionPlan $plan = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('platform_subscription_plans', 'name')->ignore($plan)],
            'description' => ['nullable', 'string'],
            'price_per_seat' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', Rule::in(['month', 'year'])],
            'minimum_seats' => ['required', 'integer', 'min:1'],
            'maximum_seats' => ['nullable', 'integer', 'gte:minimum_seats'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'stripe_product_id' => ['nullable', 'string', 'max:255'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'features_text' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['features'] = collect(preg_split('/\r\n|\r|\n/', $validated['features_text'] ?? ''))
            ->map(fn ($feature) => trim($feature))->filter()->values()->all();
        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['features_text']);

        return $validated;
    }
}
