<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'platform_subscription_plan_id', 'seats', 'status',
        'stripe_customer_id', 'stripe_subscription_id', 'stripe_checkout_session_id',
        'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at',
        'cancel_at', 'canceled_at',
    ];

    protected $casts = [
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

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true);
    }
}
