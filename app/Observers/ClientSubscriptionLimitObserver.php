<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Validation\ValidationException;

class ClientSubscriptionLimitObserver
{
    public function creating(Client $client): void
    {
        $company = Company::with('subscription.plan')->find($client->company_id);
        $subscription = $company?->subscription;
        if (! $subscription?->isActive()) {
            throw ValidationException::withMessages(['subscription' => 'An active company subscription is required.']);
        }

        $limit = $subscription->plan?->client_limit;
        if (! is_null($limit) && Client::withoutGlobalScopes()->where('company_id', $company->id)->count() >= $limit) {
            throw ValidationException::withMessages(['client_limit' => "This plan allows a maximum of {$limit} clients."]);
        }
    }
}
