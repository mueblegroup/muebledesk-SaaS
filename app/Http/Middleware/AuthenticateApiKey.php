<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
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

        $apiKey = ApiKey::query()
            ->where('key_hash', ApiKey::hashKey($plainTextKey))
            ->first();

        if (! $apiKey || ! $apiKey->isUsable($request->ip())) {
            return response()->json(['message' => 'Invalid, expired, revoked, or IP-restricted API key.'], 401);
        }

        if ($permission && ! $apiKey->canAccess($permission)) {
            return response()->json(['message' => 'This API key does not have permission: '.$permission], 403);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if ($bearer) {
            return trim($bearer);
        }

        $headerKey = $request->header('X-API-Key');
        return $headerKey ? trim($headerKey) : null;
    }
}
