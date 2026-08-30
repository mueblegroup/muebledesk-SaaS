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
        'billing_rank', 'stripe_product_id', 'stripe_price_id',
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
        'billing_rank' => 'integer',
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

        if ($configured) {
            return $features->contains($feature);
        }

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

    public function sameBillingIntervalAs(self $other): bool
    {
        return $this->duration_unit === $other->duration_unit
            && (int) $this->duration_value === (int) $other->duration_value;
    }

    /**
     * Returns 1 for upgrade, -1 for downgrade, 0 for the same tier, and null
     * when legacy plan data is too ambiguous to classify safely.
     */
    public function tierDirectionComparedTo(self $current): ?int
    {
        if ((int) $this->id === (int) $current->id) {
            return 0;
        }

        if ((int) $this->billing_rank > 0 && (int) $current->billing_rank > 0) {
            return (int) $this->billing_rank <=> (int) $current->billing_rank;
        }

        // Safe legacy fallback: price only defines tier order when the plans
        // share the same currency and billing interval. Admins should set an
        // explicit billing rank for mixed monthly/yearly or multi-currency tiers.
        if ($this->sameBillingIntervalAs($current)
            && strtoupper((string) $this->currency) === strtoupper((string) $current->currency)) {
            return ((float) $this->price) <=> ((float) $current->price);
        }

        return null;
    }

    public function getPricePerSeatAttribute(): float
    {
        return ((float) $this->price) / PHP_INT_MAX;
    }
}
