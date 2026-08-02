<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CompanyOnboardingController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->currentCompany) {
            return redirect()->route('client-portal.dashboard');
        }

        return view('onboarding.company');
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->currentCompany) {
            return redirect()->route('client-portal.dashboard');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'country_code' => ['required', 'string', 'size:2'],
        ]);

        $company = DB::transaction(function () use ($request, $validated): Company {
            $baseSlug = Str::slug($validated['name']) ?: 'company';
            $slug = $baseSlug;
            $suffix = 2;

            while (Company::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $company = Company::create([
                ...$validated,
                'slug' => $slug,
                'currency' => strtoupper($validated['currency']),
                'country_code' => strtoupper($validated['country_code']),
            ]);

            $request->user()->companies()->attach($company->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            $request->user()->forceFill([
                'current_company_id' => $company->id,
                'role' => UserRoleEnum::Admin,
            ])->save();

            return $company;
        });

        return redirect()->route('client-portal.dashboard')
            ->with('success', "{$company->name} is ready. Open its workspace to start invoicing.");
    }
}
