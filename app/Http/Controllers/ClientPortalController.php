<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function index(Request $request): View
    {
        $companies = $request->user()
            ->companies()
            ->orderBy('name')
            ->get();

        return view('portal.dashboard', [
            'companies' => $companies,
            'currentCompany' => $request->user()->currentCompany,
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

        return redirect()->route('dashboard');
    }
}
