<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'invoice_number',
        'date',
        'due_date',
        'status',
        'sub_total',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_type',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'payment_link',
        'hitpay_payment_id',
        'amount_paid',
        'locked_at',
        'quotation_id',
        'employee_id',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'locked_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function eInvoice()
    {
        return $this->hasOne(EInvoice::class)->latestOfMany();
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null
            || (float) $this->amount_paid > 0
            || $this->payments()->exists()
            || $this->eInvoice()->where('status', 'valid')->exists();
    }

    public function getPaymentStateAttribute(): string
    {
        if ((float) $this->amount_paid <= 0) return 'Unpaid';
        if ((float) $this->amount_paid >= (float) $this->total_amount) return 'Paid';
        return 'Partially Paid';
    }
}
