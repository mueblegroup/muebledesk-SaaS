<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'hosting',
        'software',
        'salary',
        'contractor',
        'marketing',
        'office',
        'transport',
        'utilities',
        'bank_fees',
        'payment_gateway_fees',
        'tax',
        'equipment',
        'professional_services',
        'other',
    ];

    protected $fillable = [
        'recorded_by_user_id',
        'expense_number',
        'expense_date',
        'category',
        'vendor',
        'description',
        'amount',
        'currency',
        'payment_method',
        'reference_number',
        'is_billable',
        'is_tax_deductible',
        'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'is_billable' => 'boolean',
        'is_tax_deductible' => 'boolean',
    ];

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public static function categoryOptions(): array
    {
        return collect(self::CATEGORIES)
            ->mapWithKeys(fn ($category) => [$category => str($category)->replace('_', ' ')->title()->toString()])
            ->all();
    }
}
