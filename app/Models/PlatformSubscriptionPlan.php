<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'price_per_seat', 'currency',
        'billing_interval', 'minimum_seats', 'maximum_seats', 'trial_days',
        'stripe_product_id', 'stripe_price_id', 'features', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price_per_seat' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }
}
