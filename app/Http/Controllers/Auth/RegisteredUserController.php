<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc,dns', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:30'],
            'job_title' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'country_code' => ['required', 'string', 'size:2'],
            'preferred_timezone' => ['required', 'timezone'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'job_title' => $validated['job_title'],
            'address' => $validated['address'],
            'country_code' => strtoupper($validated['country_code']),
            'preferred_timezone' => $validated['preferred_timezone'],
            'profile_completed_at' => now(),
            'password' => Hash::make($validated['password']),
            'role' => UserRoleEnum::Admin,
            'current_company_id' => null,
        ]);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice')
            ->with('status', 'verification-link-sent');
    }
}
