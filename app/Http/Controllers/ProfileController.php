<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ActivityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request, TwoFactorService $twoFactorService): View
    {
        $user = $request->user();
        $user->loadMissing('clients');
        $twoFactorQrSvg = null;
        $twoFactorSetupUrl = null;

        if ($user->two_factor_secret && ! $user->hasTwoFactorEnabled()) {
            $twoFactorQrSvg = $twoFactorService->qrCodeSvg($user);
            $twoFactorSetupUrl = $twoFactorService->otpauthUrl($user);
        }

        return view('profile.edit', [
            'user' => $user,
            'customerClient' => $user->isCustomer() ? $user->clients : null,
            'twoFactorQrSvg' => $twoFactorQrSvg,
            'twoFactorSetupUrl' => $twoFactorSetupUrl,
            'recoveryCodes' => session('two_factor_recovery_codes_plain'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->country_code = strtoupper($request->validated('country_code'));
        $user->profile_completed_at = now();

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            return Redirect::route('verification.notice')->with('status', 'verification-link-sent');
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateBusinessDetails(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isCustomer(), 403, 'Customer profile access only.');

        $client = $user->clients;
        abort_unless($client, 404, 'No customer profile is linked to this account.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'tin_number' => ['nullable', 'string', 'max:255'],
            'id_type' => ['nullable', Rule::in(['BRN', 'NRIC', 'PASSPORT', 'ARMY', 'OTHER'])],
            'id_number' => ['nullable', 'string', 'max:255'],
            'sst_registration_number' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:50'],
            'country_code' => ['nullable', 'string', 'max:10'],
        ]);

        $old = $client->only(array_keys($validated));
        $client->update($validated);
        $activityLogger->log('customer_profile.updated', 'Customer business/tax profile updated by customer', $client, $old, $client->fresh()->only(array_keys($validated)));

        return Redirect::route('profile.edit')->with('status', 'business-profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', ['password' => ['required', 'current_password']]);
        $user = $request->user();
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return Redirect::to('/');
    }
}
