<?php

namespace App\Models;

use App\Models\Client;
use App\Models\RecurringInvoiceItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RecurringInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'invoice_prefix',
        'frequency',
        'repeat_every',
        'repeat_unit',
        'start_date',
        'end_date',
        'sub_total',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_type',
        'tax_rate',
        'tax_amount',
        'next_invoice_date',
        'employee_id',
        'is_active',
        'total_amount',
    ];

    protected $casts = [
        'repeat_every' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_invoice_date' => 'date',
        'sub_total' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function items()
    {
        return $this->hasMany(RecurringInvoiceItem::class);
    }

    public function calculateNextInvoiceDate(Carbon $currentDate = null): Carbon
    {
        $currentDate = $currentDate ?? now();
        $date = ($this->next_invoice_date ?? $this->start_date)->copy();

        if ($date->lessThan($currentDate)) {
            while ($date->lessThan($currentDate)) {
                $date = $this->addFrequency($date);
            }
        } else {
            $date = $this->addFrequency($date);
        }

        return $date;
    }

    public function upcomingInvoiceDates(int $count = 6): Collection
    {
        $dates = collect();
        $date = ($this->next_invoice_date ?? $this->start_date)?->copy();

        if (! $date || $count < 1) {
            return $dates;
        }

        while ($date->lt(today())) {
            $date = $this->addFrequency($date);
        }

        while ($dates->count() < $count) {
            if ($this->end_date && $date->gt($this->end_date)) {
                break;
            }

            $dates->push($date->copy());
            $date = $this->addFrequency($date);
        }

        return $dates;
    }

    public function frequencyLabel(): string
    {
        if ($this->frequency !== 'custom') {
            return ucfirst($this->frequency);
        }

        $value = max(1, (int) $this->repeat_every);
        $unit = rtrim((string) ($this->repeat_unit ?: 'months'), 's');

        return 'Every '.$value.' '.$unit.($value === 1 ? '' : 's');
    }

    protected function addFrequency(Carbon $date): Carbon
    {
        if ($this->frequency === 'custom') {
            $value = max(1, (int) $this->repeat_every);

            return match ($this->repeat_unit) {
                'days' => $date->copy()->addDays($value),
                'weeks' => $date->copy()->addWeeks($value),
                'months' => $date->copy()->addMonthsNoOverflow($value),
                'years' => $date->copy()->addYearsNoOverflow($value),
                default => $date->copy()->addMonthsNoOverflow($value),
            };
        }

        return match ($this->frequency) {
            'daily' => $date->copy()->addDay(),
            'weekly' => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonthNoOverflow(),
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
