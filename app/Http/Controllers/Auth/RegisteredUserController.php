<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use DateTimeZone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use RuntimeException;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'countries' => collect(config('registration.countries', []))
                ->sortBy(fn (array $country) => $country['name'] ?? '')
                ->all(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $countries = config('registration.countries', []);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc,dns', 'max:255', 'unique:'.User::class],
            'country_code' => ['required', 'string', 'size:2', Rule::in(array_keys($countries))],
            'phone' => ['required', 'string', 'max:30'],
            'job_title' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'preferred_timezone' => ['required', 'timezone'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $countryCode = strtoupper($validated['country_code']);
        $phone = $this->normalizeInternationalPhone($countryCode, $validated['phone'], $countries);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $phone,
            'job_title' => $validated['job_title'],
            'address' => $validated['address'],
            'country_code' => $countryCode,
            'preferred_timezone' => $validated['preferred_timezone'],
            'profile_completed_at' => now(),
            'password' => Hash::make($validated['password']),
            'role' => UserRoleEnum::Admin,
            'current_company_id' => null,
        ]);

        // User implements MustVerifyEmail, so the Registered event dispatches
        // Laravel's verification notification. Verified middleware protects
        // company onboarding and billing until the signed link is completed.
        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice')
            ->with('status', 'verification-link-sent');
    }

    private function normalizeInternationalPhone(string $countryCode, string $rawPhone, array $countries): string
    {
        $dialCode = (string) data_get($countries, $countryCode.'.dial', '');
        if ($dialCode === '' || ! preg_match('/^\+[1-9]\d{0,3}$/', $dialCode)) {
            throw new RuntimeException('The selected country does not have a valid dialing code.');
        }

        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        $dialDigits = ltrim($dialCode, '+');

        // Accept users pasting either a national number (012...) or a complete
        // international number (+6012...). Store one canonical international
        // representation so billing/WhatsApp integrations don't need to guess.
        if (str_starts_with($digits, $dialDigits)) {
            $nationalDigits = substr($digits, strlen($dialDigits));
        } else {
            $nationalDigits = ltrim($digits, '0');
        }

        if ($nationalDigits === '' || strlen($dialDigits.$nationalDigits) < 7 || strlen($dialDigits.$nationalDigits) > 15) {
            abort(422, 'Please enter a valid mobile number for the selected country.');
        }

        return '+'.$dialDigits.$nationalDigits;
    }
}
