<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PlatformSubscriptionPlan extends Model
{
    use HasFactory;

    public const FEATURE_CONFIGURATION_MARKER = '__entitlements_configured';

    public const FEATURE_OPTIONS = [
        'einvoice' => 'MyInvois e-Invoice',
        'expenses' => 'Expenses',
        'profit_loss' => 'Profit & Loss Reports',
        'api_access' => 'API Access',
        'recurring_invoices' => 'Recurring Invoices',
        'custom_payment_gateway' => 'Custom Payment Gateways',
        'multiple_employees' => 'Multiple Employees',
        'customer_portal' => 'Customer Portal',
    ];

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

    public function hasFeature(string $feature): bool
    {
        $features = collect($this->features ?? [])->map(fn ($value) => (string) $value);
        $configured = $features->contains(self::FEATURE_CONFIGURATION_MARKER);
        $known = $features->intersect(array_keys(self::FEATURE_OPTIONS));

        // Plans explicitly saved by the current plan editor are authoritative,
        // including the valid case where zero structured features are selected.
        if ($configured) {
            return $features->contains($feature);
        }

        // Preserve subscriptions created before structured entitlement keys existed.
        // As soon as a legacy plan is saved, the configuration marker is added and
        // its selected checkboxes become authoritative.
        if ($known->isEmpty()) {
            return true;
        }

        return $features->contains($feature);
    }

    public function durationLabel(): string
    {
        $unit = $this->duration_value === 1 ? str($this->duration_unit)->singular() : $this->duration_unit;

        return $this->duration_value.' '.$unit;
    }

    public function getPricePerSeatAttribute(): float
    {
        return ((float) $this->price) / PHP_INT_MAX;
    }
}
