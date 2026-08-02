<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'transaction_reference',
        'transaction_id',
        'transfer_receipt_path',
        'transfer_receipt_original_name',
        'notes',
        'recorded_by_employee_id',
        'is_deposit',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'is_deposit' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_employee_id');
    }

    public function receipt()
    {
        return $this->hasOne(PaymentReceipt::class);
    }
}
