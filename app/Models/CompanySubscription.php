<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'platform_subscription_plan_id', 'status',
        'starts_at', 'ends_at', 'auto_renew', 'is_enabled', 'renewal_failures',
        'stripe_customer_id', 'stripe_subscription_id', 'stripe_checkout_session_id',
        'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at',
        'cancel_at', 'canceled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_renew' => 'boolean',
        'is_enabled' => 'boolean',
        'renewal_failures' => 'integer',
        'trial_ends_at' => 'datetime',
        'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'cancel_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'platform_subscription_plan_id');
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_enabled
            && in_array($this->status, ['active', 'trialing'], true)
            && ! $this->isExpired();
    }

    public function activateFromPlan(PlatformSubscriptionPlan $plan, ?bool $autoRenew = null): void
    {
        $startsAt = now();

        $this->fill([
            'platform_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $plan->addDuration($startsAt),
            'auto_renew' => $autoRenew ?? $plan->auto_renew_default,
            'is_enabled' => true,
            'renewal_failures' => 0,
            'canceled_at' => null,
            'cancel_at' => null,
        ])->save();
    }
}
