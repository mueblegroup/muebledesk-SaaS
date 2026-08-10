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
                ->with('warning', 'Complete your account profile before creating or managing a company.');
        }

        // The central domain is the SaaS account/company-management portal.
        // Employee memberships are intentionally excluded: workplace access is
        // through that company's subdomain, not through the central portal.
        $companies = $user->companies()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->with(['subscription.plan', 'users:id,name,email,role'])
            ->withCount('clients')
            ->orderBy('name')
            ->get();

        if ($companies->isEmpty()) {
            return redirect()->route('companies.create');
        }

        $currentCompany = $companies->firstWhere('id', $user->current_company_id) ?: $companies->first();

        if ((int) $user->current_company_id !== (int) $currentCompany->id) {
            $user->forceFill(['current_company_id' => $currentCompany->id])->save();
        }

        return view('client-portal.dashboard', [
            'companies' => $companies,
            'currentCompany' => $currentCompany,
            'rootDomain' => config('saas.root_domain'),
            'scheme' => config('saas.scheme', 'https'),
        ]);
    }

    public function switch(Request $request, Company $company): RedirectResponse
    {
        $canManage = $request->user()->companies()
            ->whereKey($company->getKey())
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();

        abort_unless($canManage, 403);

        $request->user()->forceFill(['current_company_id' => $company->getKey()])->save();

        return redirect()->away(sprintf(
            '%s://%s.%s/dashboard',
            config('saas.scheme', 'https'),
            $company->slug,
            config('saas.root_domain')
        ));
    }
}
