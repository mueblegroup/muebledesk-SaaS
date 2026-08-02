<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
