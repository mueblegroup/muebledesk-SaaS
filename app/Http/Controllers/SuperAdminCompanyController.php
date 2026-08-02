<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SuperAdminCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Company::query()
            ->with(['owners:id,name,email', 'subscription.plan'])
            ->withCount('users');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $query->whereHas('subscription', fn ($builder) => $builder->whereIn('status', ['active', 'trialing']));
            } elseif ($status === 'inactive') {
                $query->where(function ($builder) {
                    $builder->whereDoesntHave('subscription')
                        ->orWhereHas('subscription', fn ($subscription) => $subscription->whereNotIn('status', ['active', 'trialing']));
                });
            }
        }

        return view('superadmin.companies.index', [
            'companies' => $query->latest()->paginate(25)->withQueryString(),
            'counts' => [
                'all' => Company::count(),
                'active' => Company::whereHas('subscription', fn ($builder) => $builder->whereIn('status', ['active', 'trialing']))->count(),
                'without_plan' => Company::whereDoesntHave('subscription')->count(),
            ],
        ]);
    }

    public function show(Company $company): View
    {
        $company->load([
            'owners:id,name,email,phone',
            'users' => fn ($query) => $query->orderBy('name'),
            'subscription.plan',
        ]);

        return view('superadmin.companies.show', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('companies', 'slug')->ignore($company)],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        $company->update([
            ...$validated,
            'currency' => strtoupper($validated['currency']),
            'country_code' => strtoupper($validated['country_code']),
        ]);

        return back()->with('success', 'Company details updated.');
    }
}
