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
        if (! $company) {
            return;
        }

        $subscription = $company->subscription;

        // A company's first owner must be allowed to become its administrator
        // before checkout. Company onboarding attaches the owner membership first,
        // then sets current_company_id / role. All non-owner users still require an
        // active subscription, and normal plan limits apply once subscribed.
        $isOwner = $user->exists && $user->companies()
            ->whereKey($company->getKey())
            ->wherePivot('role', 'owner')
            ->exists();

        if (! $subscription?->isActive()) {
            if ($isOwner) {
                return;
            }

            throw ValidationException::withMessages(['subscription' => 'An active company subscription is required.']);
        }

        $role = $user->role?->value ?? (string) $user->getRawOriginal('role');
        $plan = $subscription->plan;
        $limit = $plan?->limitForRole($role);

        // The employee_limit remains the absolute ceiling. When the plan does not
        // include the "multiple_employees" feature, at most one employee account is
        // allowed even if an older/misconfigured numeric limit is higher.
        if ($role === 'employee' && $plan && ! $plan->hasFeature('multiple_employees')) {
            $limit = is_null($limit) ? 1 : min($limit, 1);
        }

        if (is_null($limit)) {
            return;
        }

        $count = $company->users()->where('users.role', $role)
            ->when($user->exists, fn ($query) => $query->where('users.id', '!=', $user->id))
            ->count();

        if ($count >= $limit) {
            $message = $role === 'employee' && $plan && ! $plan->hasFeature('multiple_employees')
                ? 'This plan does not include multiple employee accounts.'
                : "This plan allows a maximum of {$limit} {$role} account(s).";

            throw ValidationException::withMessages(['role' => $message]);
        }
    }
}
