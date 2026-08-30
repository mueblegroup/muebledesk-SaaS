<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'platform_subscription_plan_id', 'pending_platform_subscription_plan_id', 'status',
        'stripe_customer_id', 'stripe_subscription_id', 'stripe_subscription_schedule_id', 'stripe_checkout_session_id',
        'starts_at', 'expires_at', 'auto_renew', 'is_enabled',
        'renewal_failure_count', 'last_renewal_attempt_at', 'last_renewal_error',
        'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at',
        'pending_plan_effective_at', 'cancel_at', 'canceled_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
        'is_enabled' => 'boolean',
        'renewal_failure_count' => 'integer',
        'last_renewal_attempt_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'pending_plan_effective_at' => 'datetime',
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

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(PlatformSubscriptionPlan::class, 'pending_platform_subscription_plan_id');
    }

    public function isActive(): bool
    {
        return $this->is_enabled
            && in_array($this->status, ['active', 'trialing'], true)
            && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function activate(?\DateTimeInterface $from = null): void
    {
        $start = $from ? now()->parse($from) : now();
        $this->forceFill([
            'status' => 'active',
            'is_enabled' => true,
            'starts_at' => $start,
            'expires_at' => $this->plan?->calculateExpiry($start),
            'renewal_failure_count' => 0,
            'last_renewal_error' => null,
        ])->save();
    }

    public function extend(): void
    {
        $base = $this->expires_at && $this->expires_at->isFuture() ? $this->expires_at : now();
        $this->forceFill([
            'status' => 'active',
            'is_enabled' => true,
            'expires_at' => $this->plan?->calculateExpiry($base),
            'renewal_failure_count' => 0,
            'last_renewal_error' => null,
        ])->save();
    }

    public function getSeatsAttribute(): int
    {
        $plan = $this->relationLoaded('plan') ? $this->plan : $this->plan()->first();
        if (! $plan || is_null($plan->admin_limit) || is_null($plan->employee_limit) || is_null($plan->client_limit)) {
            return PHP_INT_MAX;
        }

        return $plan->admin_limit + $plan->employee_limit + $plan->client_limit;
    }
}
