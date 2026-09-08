<?php

namespace App\Http\Controllers;

use App\Models\Company;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompanyOnboardingController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->profile_completed_at) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Complete your account profile before creating a company.');
        }

        return view('onboarding.company', [
            'countries' => collect(config('registration.countries', []))
                ->sortBy(fn (array $country) => $country['name'] ?? '')
                ->all(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->profile_completed_at) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Complete your account profile before creating a company.');
        }

        $countries = config('registration.countries', []);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'timezone' => ['required', 'timezone'],
            'country_code' => ['required', 'string', 'size:2', Rule::in(array_keys($countries))],
        ]);

        $countryCode = strtoupper($validated['country_code']);
        $phone = filled($validated['phone'] ?? null)
            ? $this->normalizeInternationalPhone($countryCode, (string) $validated['phone'], $countries)
            : null;

        $registrationNumber = filled($validated['registration_number'] ?? null)
            ? trim((string) $validated['registration_number'])
            : null;
        $taxNumber = filled($validated['tax_number'] ?? null)
            ? trim((string) $validated['tax_number'])
            : null;
        $email = filled($validated['email'] ?? null)
            ? strtolower(trim((string) $validated['email']))
            : null;

        $company = DB::transaction(function () use ($request, $validated, $countryCode, $phone, $registrationNumber, $taxNumber, $email): Company {
            $baseSlug = Str::slug($validated['name']) ?: 'company';
            $slug = $baseSlug;
            $suffix = 2;

            while (Company::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $company = Company::create([
                'name' => trim((string) $validated['name']),
                'slug' => $slug,
                'registration_number' => $registrationNumber,
                'tax_number' => $taxNumber,
                'email' => $email,
                'phone' => $phone,
                'currency' => strtoupper($validated['currency']),
                'timezone' => $validated['timezone'],
                'country_code' => $countryCode,
            ]);

            $request->user()->companies()->attach($company->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            $request->user()->forceFill([
                'current_company_id' => $company->id,
            ])->save();

            return $company;
        });

        return redirect()->route('client-portal.billing.index', $company)
            ->with('success', "{$company->name} is ready. Choose a plan to activate your workspace.");
    }

    private function normalizeInternationalPhone(string $countryCode, string $rawPhone, array $countries): string
    {
        $dialCode = (string) data_get($countries, $countryCode.'.dial', '');
        if ($dialCode === '' || ! preg_match('/^\+[1-9]\d{0,3}$/', $dialCode)) {
            throw ValidationException::withMessages([
                'country_code' => 'The selected country does not have a valid dialing code.',
            ]);
        }

        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        $dialDigits = ltrim($dialCode, '+');

        if (str_starts_with($digits, $dialDigits)) {
            $nationalDigits = substr($digits, strlen($dialDigits));
        } else {
            $nationalDigits = ltrim($digits, '0');
        }

        $internationalDigits = $dialDigits.$nationalDigits;
        if ($nationalDigits === '' || strlen($internationalDigits) < 7 || strlen($internationalDigits) > 15) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid phone number for the selected country.',
            ]);
        }

        return '+'.$internationalDigits;
    }
}
