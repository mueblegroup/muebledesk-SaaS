<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recurring_invoice_id',
        'item_name',
        'description',
        'quantity',
        'price',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function recurringInvoice()
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    // Accessors/Mutators to ensure 'total' is always quantity * price
    public function setPriceAttribute($value)
    {
        $this->attributes['price'] = $value;
        // Ensure quantity exists before multiplication, especially on initial creation
        $this->attributes['total'] = $value * ($this->attributes['quantity'] ?? 0);
    }

    public function setQuantityAttribute($value)
    {
        $this->attributes['quantity'] = $value;
        // Ensure price exists before multiplication
        $this->attributes['total'] = $value * ($this->attributes['price'] ?? 0);
    }
}