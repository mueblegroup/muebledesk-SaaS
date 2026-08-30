<?php

namespace App\Http\Controllers;

use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperAdminPlanController extends Controller
{
    public function index(): View
    {
        return view('superadmin.plans.index', [
            'plans' => PlatformSubscriptionPlan::query()
                ->orderBy('sort_order')
                ->orderBy('price')
                ->get(),
            'featureOptions' => PlatformSubscriptionPlan::FEATURE_OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);

        PlatformSubscriptionPlan::create($validated + [
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        return back()->with('success', 'Subscription plan created.');
    }

    public function update(Request $request, PlatformSubscriptionPlan $plan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $plan);
        $billingPriceChanged = (float) $plan->price !== (float) $validated['price']
            || strtoupper((string) $plan->currency) !== strtoupper((string) $validated['currency'])
            || (int) $plan->duration_value !== (int) $validated['duration_value']
            || (string) $plan->duration_unit !== (string) $validated['duration_unit'];
        $hasPendingChanges = CompanySubscription::where('pending_platform_subscription_plan_id', $plan->id)->exists();

        if ($billingPriceChanged && $hasPendingChanges) {
            return back()->withErrors([
                'price' => 'This plan is already referenced by a pending Stripe plan change. Wait for that change to complete or clear it before changing price, currency, or billing duration.',
            ]);
        }

        $payload = $validated + [
            'slug' => $this->uniqueSlug($validated['name'], $plan),
        ];

        if ($billingPriceChanged) {
            $payload['stripe_price_id'] = null;
        }

        $plan->update($payload);

        return back()->with('success', 'Subscription plan updated.');
    }

    public function destroy(PlatformSubscriptionPlan $plan): RedirectResponse
    {
        $hasPendingChanges = CompanySubscription::where('pending_platform_subscription_plan_id', $plan->id)->exists();
        abort_if(
            $plan->subscriptions()->exists() || $hasPendingChanges,
            422,
            'Plans used by active or pending subscriptions cannot be deleted. Disable the plan instead.'
        );

        $plan->delete();

        return back()->with('success', 'Subscription plan deleted.');
    }

    private function validatePlan(Request $request, ?PlatformSubscriptionPlan $plan = null): array
    {
        $durationUnit = (string) $request->input('duration_unit');
        $maxDuration = match ($durationUnit) {
            'days' => 1095,
            'months' => 36,
            'years' => 3,
            default => 1,
        };

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('platform_subscription_plans', 'name')->ignore($plan)],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'duration_value' => ['required', 'integer', 'min:1', 'max:'.$maxDuration],
            'duration_unit' => ['required', Rule::in(['days', 'months', 'years'])],
            'admin_limit' => ['nullable', 'integer', 'min:0'],
            'employee_limit' => ['nullable', 'integer', 'min:0'],
            'client_limit' => ['nullable', 'integer', 'min:0'],
            'feature_keys' => ['nullable', 'array'],
            'feature_keys.*' => ['string', Rule::in(array_keys(PlatformSubscriptionPlan::FEATURE_OPTIONS))],
            'features_text' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'billing_rank' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'auto_renew_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $displayFeatures = collect(preg_split('/\r\n|\r|\n/', $validated['features_text'] ?? ''))
            ->map(fn (string $feature) => trim($feature))
            ->filter()
            ->reject(fn (string $feature) => array_key_exists($feature, PlatformSubscriptionPlan::FEATURE_OPTIONS))
            ->reject(fn (string $feature) => $feature === PlatformSubscriptionPlan::FEATURE_CONFIGURATION_MARKER);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['features'] = collect($validated['feature_keys'] ?? [])
            ->merge($displayFeatures)
            ->push(PlatformSubscriptionPlan::FEATURE_CONFIGURATION_MARKER)
            ->unique()
            ->values()
            ->all();
        $validated['auto_renew_default'] = $request->boolean('auto_renew_default');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['billing_rank'] = $validated['billing_rank'] ?? 0;

        unset($validated['feature_keys'], $validated['features_text']);

        return $validated;
    }

    private function uniqueSlug(string $name, ?PlatformSubscriptionPlan $ignore = null): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $suffix = 2;

        while (PlatformSubscriptionPlan::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
