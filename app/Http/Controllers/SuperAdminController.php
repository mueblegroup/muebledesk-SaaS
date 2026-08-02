<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function users(Request $request): View
    {
        $query = User::query()
            ->with(['companies:id,name,slug', 'currentCompany:id,name,slug'])
            ->withCount('companies');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            if (in_array($role, array_column(UserRoleEnum::cases(), 'value'), true)) {
                $query->where('role', $role);
            }
        }

        if ($companyId = $request->integer('company_id')) {
            $query->whereHas('companies', fn ($builder) => $builder->whereKey($companyId));
        }

        return view('superadmin.users.index', [
            'users' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'roles' => UserRoleEnum::cases(),
            'counts' => [
                'all' => User::count(),
                'superadmin' => User::where('role', UserRoleEnum::SuperAdmin->value)->count(),
                'admin' => User::where('role', UserRoleEnum::Admin->value)->count(),
                'employee' => User::where('role', UserRoleEnum::Employee->value)->count(),
                'customer' => User::where('role', UserRoleEnum::Customer->value)->count(),
            ],
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $this->validateUser($request);
        $companyIds = collect($validated['company_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $membershipRole = $validated['membership_role'] ?? 'member';

        DB::transaction(function () use ($validated, $companyIds, $membershipRole) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'email_verified_at' => now(),
                'current_company_id' => $companyIds->first(),
            ]);

            if ($validated['role'] !== UserRoleEnum::SuperAdmin->value && $companyIds->isNotEmpty()) {
                $user->companies()->sync($companyIds->mapWithKeys(fn ($id) => [
                    $id => ['role' => $membershipRole, 'joined_at' => now()],
                ])->all());
            }
        });

        return back()->with('success', 'User account created.');
    }

    public function editUser(User $user): View
    {
        $user->load('companies:id,name,slug');

        return view('superadmin.users.edit', [
            'managedUser' => $user,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'roles' => UserRoleEnum::cases(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);
        $companyIds = collect($validated['company_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $membershipRole = $validated['membership_role'] ?? 'member';

        if ($request->user()->is($user) && $validated['role'] !== UserRoleEnum::SuperAdmin->value) {
            return back()->withErrors(['role' => 'You cannot remove your own superadmin access.'])->withInput();
        }

        if ($user->isSuperAdmin()
            && $validated['role'] !== UserRoleEnum::SuperAdmin->value
            && User::where('role', UserRoleEnum::SuperAdmin->value)->count() <= 1) {
            return back()->withErrors(['role' => 'The last superadmin cannot be downgraded.'])->withInput();
        }

        DB::transaction(function () use ($validated, $companyIds, $membershipRole, $user) {
            $updates = [
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'role' => $validated['role'],
                'email_verified_at' => $validated['email_verified'] ? ($user->email_verified_at ?? now()) : null,
            ];

            if (! empty($validated['password'])) {
                $updates['password'] = Hash::make($validated['password']);
            }

            if ($validated['role'] === UserRoleEnum::SuperAdmin->value) {
                $updates['current_company_id'] = null;
                $user->companies()->detach();
            } else {
                $updates['current_company_id'] = $companyIds->contains($user->current_company_id)
                    ? $user->current_company_id
                    : $companyIds->first();

                $user->companies()->sync($companyIds->mapWithKeys(fn ($id) => [
                    $id => ['role' => $membershipRole, 'joined_at' => now()],
                ])->all());
            }

            $user->update($updates);
        });

        return redirect()->route('superadmin.users.index')->with('success', 'User account updated.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        abort_if($user->isSuperAdmin() && User::where('role', UserRoleEnum::SuperAdmin->value)->count() <= 1, 422, 'The last superadmin cannot be deleted.');

        $hasOwnedCompanies = $user->companies()->wherePivot('role', 'owner')->exists();
        abort_if($hasOwnedCompanies, 422, 'Transfer ownership of this user’s companies before deleting the account.');

        $hasBusinessRecords = $user->invoices()->exists()
            || $user->quotations()->exists()
            || $user->recurringInvoices()->exists();
        abort_if($hasBusinessRecords, 422, 'This user owns business records. Reassign or archive the account instead of deleting it.');

        DB::transaction(function () use ($user) {
            $user->companies()->detach();
            $user->delete();
        });

        return back()->with('success', 'User account deleted.');
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

    private function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRules = $user
            ? ['nullable', 'confirmed', Password::defaults()]
            : ['required', 'confirmed', Password::defaults()];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::enum(UserRoleEnum::class)],
            'password' => $passwordRules,
            'email_verified' => ['nullable', 'boolean'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', Rule::exists('companies', 'id')],
            'membership_role' => ['nullable', Rule::in(['owner', 'admin', 'member'])],
        ]) + ['email_verified' => $request->boolean('email_verified')];
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
