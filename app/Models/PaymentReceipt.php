<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReceipt extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'payment_id', 'receipt_number', 'date', 'amount',
    ];

    protected $casts = ['date' => 'date'];

    protected static function booted(): void
    {
        static::creating(function (PaymentReceipt $receipt): void {
            if (! $receipt->company_id && $receipt->payment_id) {
                $receipt->company_id = Payment::withoutGlobalScopes()
                    ->whereKey($receipt->payment_id)
                    ->value('company_id');
            }
        });
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
