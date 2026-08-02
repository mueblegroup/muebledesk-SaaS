<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SubscriptionRoleLimitObserver
{
    public function creating(User $user): void
    {
        $this->enforceUserRole($user);
    }

    public function updating(User $user): void
    {
        if ($user->isDirty(['role', 'current_company_id'])) {
            $this->enforceUserRole($user);
        }
    }

    public function creatingClient(Client $client): void
    {
        $company = Company::find($client->company_id);
        $limit = $company?->subscription?->plan?->client_limit;
        if ($company && ! is_null($limit) && Client::withoutGlobalScopes()->where('company_id', $company->id)->count() >= $limit) {
            throw ValidationException::withMessages(['client_limit' => "This plan allows a maximum of {$limit} clients."]);
        }
    }

    private function enforceUserRole(User $user): void
    {
        if (! $user->current_company_id || $user->getRawOriginal('role') === 'superadmin' || $user->role?->value === 'superadmin') {
            return;
        }

        $company = Company::with('subscription.plan')->find($user->current_company_id);
        $subscription = $company?->subscription;
        if (! $subscription?->isActive()) {
            throw ValidationException::withMessages(['subscription' => 'An active company subscription is required.']);
        }

        $role = $user->role?->value ?? (string) $user->getRawOriginal('role');
        $limit = $subscription->plan?->limitForRole($role);
        if (is_null($limit)) {
            return;
        }

        $count = $company->users()->where('users.role', $role)
            ->when($user->exists, fn ($query) => $query->where('users.id', '!=', $user->id))
            ->count();

        if ($count >= $limit) {
            throw ValidationException::withMessages(['role' => "This plan allows a maximum of {$limit} {$role} account(s)."]);
        }
    }
}
