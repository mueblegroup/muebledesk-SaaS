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
        if ($relation = $this->getRelation('users')) {
            return $relation->where('role', $role)->count();
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
}
