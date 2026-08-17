<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $plainTextKey = $this->extractKey($request);

        if (! $plainTextKey) {
            return response()->json(['message' => 'Missing API key. Use Authorization: Bearer <api_key> or X-API-Key.'], 401);
        }

        $apiKey = ApiKey::withoutGlobalScopes()
            ->where('key_hash', ApiKey::hashKey($plainTextKey))
            ->first();

        if (! $apiKey || ! $apiKey->company_id || ! $apiKey->isUsable($request->ip())) {
            return response()->json(['message' => 'Invalid, expired, revoked, or IP-restricted API key.'], 401);
        }

        $company = Company::with('subscription.plan')->find($apiKey->company_id);
        if (! $company) {
            return response()->json(['message' => 'The API key company no longer exists.'], 401);
        }

        $subscription = $company->subscription;
        if (! $subscription?->isActive() || ! $subscription->plan) {
            return response()->json(['message' => 'An active subscription is required.'], 402);
        }

        if (! $subscription->plan->hasFeature('api_access')) {
            return response()->json(['message' => 'API access is not included in the current subscription plan.'], 403);
        }

        app()->instance(Company::class, $company);
        app()->instance('currentCompany', $company);
        $request->attributes->set('currentCompany', $company);
        $request->attributes->set('api_key', $apiKey);

        if ($permission && ! $apiKey->canAccess($permission)) {
            return response()->json(['message' => 'This API key does not have permission: '.$permission], 403);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $key = $request->bearerToken() ?: $request->header('X-API-Key');

        return $key ? trim($key) : null;
    }
}
