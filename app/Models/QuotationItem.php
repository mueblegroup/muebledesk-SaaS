<?php

namespace App\Models;

use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'item_name',
        'description',
        'price',
        'quantity',
        'total',
    ];

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['description'] = app(RichTextSanitizer::class)->clean($value);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
