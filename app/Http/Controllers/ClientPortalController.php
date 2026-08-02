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
        $companies = $request->user()
            ->companies()
            ->orderBy('name')
            ->get();

        if ($companies->isEmpty()) {
            return redirect()->route('companies.create');
        }

        return view('client-portal.dashboard', [
            'companies' => $companies,
            'currentCompany' => $request->user()->currentCompany,
            'rootDomain' => config('saas.root_domain'),
            'scheme' => config('saas.scheme', 'https'),
        ]);
    }

    public function switch(Request $request, Company $company): RedirectResponse
    {
        abort_unless(
            $request->user()->companies()->whereKey($company->getKey())->exists(),
            403
        );

        $request->user()->forceFill([
            'current_company_id' => $company->getKey(),
        ])->save();

        $workspaceUrl = sprintf(
            '%s://%s.%s/dashboard',
            config('saas.scheme', 'https'),
            $company->slug,
            config('saas.root_domain')
        );

        return redirect()->away($workspaceUrl);
    }
}
