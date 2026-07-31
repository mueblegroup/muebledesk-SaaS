<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'quote_number',
        'date',
        'expiry_date',
        'status',
        'sub_total',
        'discount_type',
        'discount_value',
        'total_amount',
        'tax_type',
        'tax_rate',
        'tax_amount',
        'employee_id',
    ];

    protected $casts = [
        'date' => 'date',
        'expiry_date' => 'date',
        'sub_total' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'converted_to_invoice' || $this->invoices()->where(function ($query) {
            $query->whereNotNull('locked_at')->orWhere('amount_paid', '>', 0)->orWhereHas('payments');
        })->exists();
    }

    public function getDiscountAmountAttribute()
    {
        if ($this->discount_type === 'percentage') {
            return ($this->sub_total * $this->discount_value) / 100;
        }

        if ($this->discount_type === 'fixed') {
            return $this->discount_value;
        }

        return 0;
    }
}
