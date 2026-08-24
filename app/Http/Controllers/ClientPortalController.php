<?php

namespace App\Http\Controllers;

use App\Models\Company;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
            'timezones' => DateTimeZone::listIdentifiers(),
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

    public function updateTimezone(Request $request, Company $company): RedirectResponse
    {
        $canManage = $request->user()->companies()
            ->whereKey($company->getKey())
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();

        abort_unless($canManage, 403);

        $validated = $request->validate([
            'timezone' => ['required', 'timezone'],
        ]);

        $oldTimezone = $company->timezone ?: 'UTC';
        $newTimezone = $validated['timezone'];

        if ($oldTimezone === $newTimezone) {
            return back()->with('success', 'Company timezone is already set to '.$newTimezone.'.');
        }

        // Never rewrite recurring next_invoice_date values when a timezone
        // changes. Those are business occurrence dates, not UTC timestamps.
        // After switching timezone, immediately run a company-scoped catch-up
        // pass. The hourly scheduler remains the fallback if this pass fails.
        $company->update([
            'timezone' => $newTimezone,
        ]);

        try {
            $exitCode = Artisan::call('invoices:generate-recurring', [
                '--company' => (string) $company->getKey(),
            ]);

            if ($exitCode !== 0) {
                Log::warning('Immediate recurring invoice catch-up returned a non-zero exit code after timezone update.', [
                    'company_id' => $company->getKey(),
                    'old_timezone' => $oldTimezone,
                    'new_timezone' => $newTimezone,
                    'exit_code' => $exitCode,
                    'output' => Artisan::output(),
                ]);

                return back()
                    ->with('success', 'Company timezone updated successfully.')
                    ->with('warning', 'The immediate recurring invoice check could not complete. The hourly scheduler will retry automatically.');
            }
        } catch (\Throwable $e) {
            Log::error('Immediate recurring invoice catch-up failed after timezone update.', [
                'company_id' => $company->getKey(),
                'old_timezone' => $oldTimezone,
                'new_timezone' => $newTimezone,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return back()
                ->with('success', 'Company timezone updated successfully.')
                ->with('warning', 'The immediate recurring invoice check could not complete. The hourly scheduler will retry automatically.');
        }

        return back()->with(
            'success',
            'Company timezone updated successfully. All recurring invoices due in the new timezone were checked and caught up.'
        );
    }
}
