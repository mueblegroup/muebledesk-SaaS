<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthenticationController extends Controller
{
    private const PROVIDERS = ['google', 'microsoft', 'apple'];

    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        $driver = Socialite::driver($provider);
        if ($provider === 'google') {
            $driver->scopes(['openid', 'profile', 'email']);
        }

        return $driver->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('login')->with('error', ucfirst($provider).' sign-in could not be completed. Please try again.');
        }

        $providerId = (string) $socialUser->getId();
        $email = strtolower(trim((string) $socialUser->getEmail()));

        if ($providerId === '' || $email === '') {
            return redirect()->route('login')->with('error', ucfirst($provider).' did not return a usable verified email address.');
        }

        $user = DB::transaction(function () use ($provider, $providerId, $email, $socialUser): User {
            $account = SocialAccount::where('provider', $provider)->where('provider_user_id', $providerId)->first();
            if ($account) {
                return $account->user;
            }

            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if (! $user) {
                $name = trim((string) $socialUser->getName()) ?: Str::before($email, '@');
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::random(64),
                    'role' => UserRoleEnum::Admin,
                    'email_verified_at' => now(),
                    'preferred_timezone' => 'Asia/Kuala_Lumpur',
                ]);
            } elseif (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'provider_email' => $email,
                'avatar_url' => $socialUser->getAvatar(),
            ]);

            return $user;
        });

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('client-portal.dashboard'))
            ->with('success', 'Signed in with '.ucfirst($provider).'.');
    }
}
