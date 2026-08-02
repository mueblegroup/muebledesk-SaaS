<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringInvoiceItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'recurring_invoice_id', 'item_name', 'description',
        'quantity', 'price', 'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (RecurringInvoiceItem $item): void {
            if (! $item->company_id && $item->recurring_invoice_id) {
                $item->company_id = RecurringInvoice::withoutGlobalScopes()
                    ->whereKey($item->recurring_invoice_id)
                    ->value('company_id');
            }
        });
    }

    public function recurringInvoice()
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function setPriceAttribute($value): void
    {
        $this->attributes['price'] = $value;
        $this->attributes['total'] = $value * ($this->attributes['quantity'] ?? 0);
    }

    public function setQuantityAttribute($value): void
    {
        $this->attributes['quantity'] = $value;
        $this->attributes['total'] = $value * ($this->attributes['price'] ?? 0);
    }
}
