<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'registration_number',
        'tax_number',
        'email',
        'phone',
        'currency',
        'timezone',
        'country_code',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class);
    }

    public function roleUsage(string $role): int
    {
        if ($this->relationLoaded('users')) {
            return $this->users->where('role', $role)->count();
        }

        return $this->users()->where('users.role', $role)->count();
    }

    public function clientUsage(): int
    {
        if (array_key_exists('clients_count', $this->getAttributes())) {
            return (int) $this->clients_count;
        }

        return $this->clients()->count();
    }

    public function roleLimit(string $role): ?int
    {
        $subscription = $this->subscription;
        $plan = $subscription?->plan;

        if (! $subscription?->isActive() || ! $plan) {
            return null;
        }

        return $plan->limitForRole($role);
    }

    public function roleUsagePercentage(string $role): ?int
    {
        $limit = $this->roleLimit($role);

        if ($limit === null) {
            return null;
        }

        if ($limit <= 0) {
            return $this->roleUsage($role) > 0 ? 100 : 0;
        }

        return min(100, (int) floor(($this->roleUsage($role) / $limit) * 100));
    }

    public function planUsage(): array
    {
        return collect([
            'admin' => 'Admins',
            'employee' => 'Employees',
            'customer' => 'Customers',
        ])->mapWithKeys(function (string $label, string $role) {
            $used = $this->roleUsage($role);
            $limit = $this->roleLimit($role);
            $percentage = $this->roleUsagePercentage($role);

            return [$role => [
                'role' => $role,
                'label' => $label,
                'used' => $used,
                'limit' => $limit,
                'percentage' => $percentage,
                'near_limit' => $limit !== null && $percentage !== null && $percentage >= 80 && $used < $limit,
                'at_limit' => $limit !== null && $used >= $limit,
            ]];
        })->all();
    }

    public function seatLimit(): ?int
    {
        $plan = $this->subscription?->plan;

        if (! $this->subscription?->isActive() || ! $plan) {
            return null;
        }

        if ($plan->admin_limit === null || $plan->employee_limit === null) {
            return null;
        }

        return $plan->admin_limit + $plan->employee_limit;
    }

    public function seatsUsed(): int
    {
        return $this->roleUsage('admin') + $this->roleUsage('employee');
    }

    public function seatUsagePercentage(): ?int
    {
        $limit = $this->seatLimit();

        if ($limit === null) {
            return null;
        }

        if ($limit <= 0) {
            return $this->seatsUsed() > 0 ? 100 : 0;
        }

        return min(100, (int) floor(($this->seatsUsed() / $limit) * 100));
    }
}
