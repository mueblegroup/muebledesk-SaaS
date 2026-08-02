<?php

namespace App\Http\Controllers;

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

        $plan->update($validated + [
            'slug' => $this->uniqueSlug($validated['name'], $plan),
        ]);

        return back()->with('success', 'Subscription plan updated.');
    }

    public function destroy(PlatformSubscriptionPlan $plan): RedirectResponse
    {
        abort_if($plan->subscriptions()->exists(), 422, 'Plans with subscriptions cannot be deleted. Disable the plan instead.');

        $plan->delete();

        return back()->with('success', 'Subscription plan deleted.');
    }

    private function validatePlan(Request $request, ?PlatformSubscriptionPlan $plan = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('platform_subscription_plans', 'name')->ignore($plan)],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'duration_value' => ['required', 'integer', 'min:1', 'max:3650'],
            'duration_unit' => ['required', Rule::in(['day', 'month', 'year'])],
            'admin_limit' => ['nullable', 'integer', 'min:0'],
            'employee_limit' => ['nullable', 'integer', 'min:0'],
            'client_limit' => ['nullable', 'integer', 'min:0'],
            'features_text' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'auto_renew_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['currency'] = strtoupper($validated['currency']);
        $validated['features'] = collect(preg_split('/\r\n|\r|\n/', $validated['features_text'] ?? ''))
            ->map(fn (string $feature) => trim($feature))
            ->filter()
            ->values()
            ->all();
        $validated['auto_renew_default'] = $request->boolean('auto_renew_default');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        unset($validated['features_text']);

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
