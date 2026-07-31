<?php

namespace App\Models;

use App\Services\RichTextSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
