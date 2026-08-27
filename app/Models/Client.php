<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Client extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'client_type',
        'contact_person',
        'email',
        'billing_email',
        'phone',
        'website',
        'address',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postcode',
        'country_code',
        'tin_number',
        'id_type',
        'id_number',
        'sst_registration_number',
        'payment_terms_days',
        'notes',
        'user_id',
        'employee_id',
    ];

    protected $casts = [
        'payment_terms_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Client $client): void {
            if (! app()->bound('currentCompany')) {
                return;
            }

            $company = app('currentCompany');
            if (! $company instanceof Company) {
                return;
            }

            $company->loadMissing('subscription.plan');
            $subscription = $company->subscription;

            if (! $subscription?->isActive()) {
                throw ValidationException::withMessages([
                    'plan' => 'An active platform subscription is required before adding customers.',
                ]);
            }

            $limit = $company->roleLimit('customer');
            if ($limit === null) {
                return;
            }

            $used = static::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->getKey())
                ->count();

            if ($used >= $limit) {
                throw ValidationException::withMessages([
                    'plan' => "Your {$subscription->plan?->name} plan allows a maximum of {$limit} customer account(s). Remove an existing customer or upgrade the company plan to add another.",
                ]);
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customerUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Invoice::class);
    }

    public function getBillingAddressAttribute(): string
    {
        $structured = collect([
            $this->address_line_1,
            $this->address_line_2,
            trim(collect([$this->postcode, $this->city])->filter()->implode(' ')),
            $this->state,
            $this->country_code,
        ])->filter()->implode("\n");

        return $structured !== '' ? $structured : (string) $this->address;
    }
}
