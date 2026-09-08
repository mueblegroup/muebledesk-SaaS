<?php

namespace App\Models;

use App\Enums\UserRoleEnum;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'current_company_id', 'name', 'email', 'phone', 'password', 'role',
        'job_title', 'address', 'country_code', 'preferred_timezone',
        'profile_completed_at', 'whatsapp_verified_at', 'two_factor_secret',
        'two_factor_recovery_codes', 'two_factor_enabled_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'profile_completed_at' => 'datetime',
        'whatsapp_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRoleEnum::class,
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_enabled_at' => 'datetime',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot(['role', 'joined_at'])->withTimestamps();
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function ownsCompany(?Company $company = null): bool
    {
        $company ??= $this->currentCompany;
        return $company !== null && $this->companies()->whereKey($company->getKey())->wherePivot('role', 'owner')->exists();
    }

    /**
     * Maximum number of companies this account may own.
     *
     * The first company can always be created before a subscription exists.
     * Once the account owns a company, active owned-company subscriptions define
     * the account-wide allowance. The highest finite allowance wins; if any
     * active owned-company plan is unlimited (NULL), the account is unlimited.
     */
    public function companyCreationLimit(): ?int
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $ownedCompanies = $this->companies()
            ->wherePivot('role', 'owner')
            ->with('subscription.plan')
            ->get();

        if ($ownedCompanies->isEmpty()) {
            return 1;
        }

        $activePlans = $ownedCompanies
            ->map(fn (Company $company) => $company->subscription)
            ->filter(fn ($subscription) => $subscription?->isActive() && $subscription->plan)
            ->map(fn ($subscription) => $subscription->plan)
            ->values();

        if ($activePlans->isEmpty()) {
            return 1;
        }

        if ($activePlans->contains(fn (PlatformSubscriptionPlan $plan) => is_null($plan->company_limit))) {
            return null;
        }

        return max(1, (int) $activePlans->max('company_limit'));
    }

    public function ownedCompanyCount(): int
    {
        return $this->companies()->wherePivot('role', 'owner')->count();
    }

    public function canCreateCompany(): bool
    {
        $limit = $this->companyCreationLimit();
        return is_null($limit) || $this->ownedCompanyCount() < $limit;
    }

    public function clients() { return $this->hasOne(Client::class, 'user_id'); }
    public function quotations() { return $this->hasMany(Quotation::class, 'employee_id'); }
    public function invoices() { return $this->hasMany(Invoice::class, 'employee_id'); }
    public function recurringInvoices() { return $this->hasMany(RecurringInvoice::class, 'employee_id'); }

    /**
     * Resolve this user's role inside the currently resolved company workspace.
     *
     * The central SaaS portal keeps using the account-level role for legacy and
     * superadmin behaviour, while tenant subdomains derive permissions from the
     * company membership pivot (owner/admin/member) or the linked Client record.
     */
    public function workspaceRole(?Company $company = null): ?string
    {
        $company ??= app()->bound('currentCompany') ? app('currentCompany') : null;

        if (! $company) {
            return $this->role?->value;
        }

        $membership = $this->companies()
            ->whereKey($company->getKey())
            ->first();

        if ($membership) {
            return in_array($membership->pivot->role, ['owner', 'admin'], true)
                ? 'admin'
                : 'employee';
        }

        if ($this->clients()->where('company_id', $company->getKey())->exists()) {
            return 'customer';
        }

        return null;
    }

    public function hasTwoFactorEnabled(): bool { return ! empty($this->two_factor_secret) && ! is_null($this->two_factor_enabled_at); }
    public function isSuperAdmin(): bool { return $this->role === UserRoleEnum::SuperAdmin; }
    public function isAdmin(): bool { return $this->workspaceRole() === 'admin'; }
    public function isEmployee(): bool { return $this->workspaceRole() === 'employee'; }
    public function isCustomer(): bool { return $this->workspaceRole() === 'customer'; }
}
