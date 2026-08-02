<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function subscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class);
    }

    public function seatLimit(): ?int
    {
        return $this->subscription?->isActive() ? $this->subscription->seats : null;
    }

    public function seatsUsed(): int
    {
        return $this->users()->count();
    }
}
