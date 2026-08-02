<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'quotation_id',
        'item_name',
        'description',
        'price',
        'quantity',
        'total',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuotationItem $item): void {
            if (! $item->company_id && $item->quotation_id) {
                $item->company_id = Quotation::withoutGlobalScopes()->whereKey($item->quotation_id)->value('company_id');
            }
        });
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['description'] = app(RichTextSanitizer::class)->clean($value);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
