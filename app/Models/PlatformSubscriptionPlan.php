<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PlatformSubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency',
        'duration_value', 'duration_unit', 'admin_limit', 'employee_limit',
        'client_limit', 'auto_renew_default', 'features', 'is_active', 'sort_order',
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

    public function calculateExpiry(?Carbon $from = null): Carbon
    {
        $from ??= now();

        return match ($this->duration_unit) {
            'days' => $from->copy()->addDays($this->duration_value),
            'years' => $from->copy()->addYears($this->duration_value),
            default => $from->copy()->addMonths($this->duration_value),
        };
    }

    public function limitForRole(string $role): ?int
    {
        return match ($role) {
            'admin' => $this->admin_limit,
            'employee' => $this->employee_limit,
            'customer' => $this->client_limit,
            default => null,
        };
    }

    public function durationLabel(): string
    {
        return $this->duration_value.' '.str($this->duration_unit)->singular($this->duration_value === 1)->toString();
    }
}
