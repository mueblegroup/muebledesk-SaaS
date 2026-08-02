<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformSubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency',
        'duration_value', 'duration_unit', 'admin_limit', 'employee_limit',
        'client_limit', 'auto_renew_default', 'trial_days', 'features',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'admin_limit' => 'integer',
        'employee_limit' => 'integer',
        'client_limit' => 'integer',
        'duration_value' => 'integer',
        'auto_renew_default' => 'boolean',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function addDuration(CarbonInterface $date): CarbonInterface
    {
        return match ($this->duration_unit) {
            'day' => $date->copy()->addDays($this->duration_value),
            'year' => $date->copy()->addYears($this->duration_value),
            default => $date->copy()->addMonths($this->duration_value),
        };
    }

    public function limitLabel(?int $limit): string
    {
        return $limit === null ? 'Unlimited' : (string) $limit;
    }
}
