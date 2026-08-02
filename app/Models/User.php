<?php

namespace App\Models;

use App\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'current_company_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'job_title',
        'address',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRoleEnum::class,
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_enabled_at' => 'datetime',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function ownsCompany(?Company $company = null): bool
    {
        $company ??= $this->currentCompany;

        return $company !== null
            && $this->companies()
                ->whereKey($company->getKey())
                ->wherePivot('role', 'owner')
                ->exists();
    }

    public function clients()
    {
        return $this->hasOne(Client::class, 'user_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'employee_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'employee_id');
    }

    public function recurringInvoices()
    {
        return $this->hasMany(RecurringInvoice::class, 'employee_id');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->two_factor_secret) && ! is_null($this->two_factor_enabled_at);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRoleEnum::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRoleEnum::Admin;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRoleEnum::Employee;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRoleEnum::Customer;
    }
}
