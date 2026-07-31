<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    public function index()
    {
        $apiKeys = ApiKey::query()
            ->with('user')
            ->latest()
            ->paginate(20);

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);
        $permissions = ApiKey::AVAILABLE_PERMISSIONS;

        return view('admin.api-keys.index', compact('apiKeys', 'users', 'permissions'));
    }

    public function store(Request $request, ActivityLogger $activityLogger)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'exists:users,id'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in(array_merge(['*'], ApiKey::AVAILABLE_PERMISSIONS))],
            'allowed_ips' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $plainTextKey = ApiKey::generatePlainTextKey();
        $allowedIps = collect(explode(',', (string) ($validated['allowed_ips'] ?? '')))
            ->map(fn ($ip) => trim($ip))
            ->filter()
            ->values()
            ->all();

        $apiKey = ApiKey::create([
            'name' => $validated['name'],
            'key_hash' => ApiKey::hashKey($plainTextKey),
            'key_prefix' => ApiKey::prefixFor($plainTextKey),
            'user_id' => $validated['user_id'] ?? null,
            'permissions' => $validated['permissions'],
            'allowed_ips' => $allowedIps,
            'expires_at' => ! empty($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
        ]);

        $activityLogger->log('api_key.created', 'API key created: '.$apiKey->name, $apiKey, [], [
            'name' => $apiKey->name,
            'permissions' => $apiKey->permissions,
            'allowed_ips' => $apiKey->allowed_ips,
            'expires_at' => optional($apiKey->expires_at)->toDateTimeString(),
        ]);

        return redirect()
            ->route('admin.api-keys.index')
            ->with('success', 'API key created. Copy it now; it will not be shown again.')
            ->with('plain_api_key', $plainTextKey);
    }

    public function revoke(ApiKey $apiKey, ActivityLogger $activityLogger)
    {
        if (! $apiKey->revoked_at) {
            $apiKey->update(['revoked_at' => now()]);
            $activityLogger->log('api_key.revoked', 'API key revoked: '.$apiKey->name, $apiKey);
        }

        return redirect()->route('admin.api-keys.index')->with('success', 'API key revoked.');
    }

    public function destroy(ApiKey $apiKey, ActivityLogger $activityLogger)
    {
        $old = $apiKey->toArray();
        $apiKey->delete();
        $activityLogger->log('api_key.deleted', 'API key deleted', null, $old, []);

        return redirect()->route('admin.api-keys.index')->with('success', 'API key deleted.');
    }
}
