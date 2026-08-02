<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'item_name',
        'description',
        'price',
        'quantity',
        'total',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvoiceItem $item): void {
            if (! $item->company_id && $item->invoice_id) {
                $item->company_id = Invoice::withoutGlobalScopes()->whereKey($item->invoice_id)->value('company_id');
            }
        });
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['description'] = app(RichTextSanitizer::class)->clean($value);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
