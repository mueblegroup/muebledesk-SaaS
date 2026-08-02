<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->profile_completed_at) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Complete your client identity details before creating a company.');
        }

        $companies = $user->companies()
            ->with(['subscription.plan', 'users:id,name,email,role'])
            ->withCount('clients')
            ->orderBy('name')
            ->get();

        if ($companies->isEmpty()) {
            return redirect()->route('companies.create');
        }

        return view('client-portal.dashboard', [
            'companies' => $companies,
            'currentCompany' => $user->currentCompany,
            'rootDomain' => config('saas.root_domain'),
            'scheme' => config('saas.scheme', 'https'),
        ]);
    }

    public function switch(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->companies()->whereKey($company->getKey())->exists(), 403);
        $request->user()->forceFill(['current_company_id' => $company->getKey()])->save();

        return redirect()->away(sprintf(
            '%s://%s.%s/dashboard',
            config('saas.scheme', 'https'),
            $company->slug,
            config('saas.root_domain')
        ));
    }
}
