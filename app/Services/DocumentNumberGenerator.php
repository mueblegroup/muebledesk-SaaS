<?php

namespace App\Services;

use App\Models\NumberSequence;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    public function generate(
        Model $model,
        string $column,
        string $prefixSetting,
        string $formatSetting,
        string $defaultPrefix,
        CarbonInterface $documentDate,
        int $employeeId,
        ?string $documentType = null
    ): string {
        $prefix = rtrim(trim((string) Setting::get($prefixSetting, $defaultPrefix)), '-: /');
        $prefix = $prefix !== '' ? $prefix : $defaultPrefix;
        $format = Setting::get($formatSetting, 'sequential_yearly');
        $documentType = $documentType ?: $column;

        return match ($format) {
            'sequential_global' => $this->sequentialNumber($model, $column, $documentType, 'global', $prefix),
            'sequential_monthly' => $this->sequentialNumber($model, $column, $documentType, $documentDate->format('Ym'), $prefix.'-'.$documentDate->format('Ym')),
            'sequential_yearly' => $this->sequentialNumber($model, $column, $documentType, $documentDate->format('Y'), $prefix.'-'.$documentDate->format('Y')),
            'date' => $this->uniqueDateNumber($model, $column, $prefix.'-'.$documentDate->format('Ymd')),
            'timestamp' => $prefix.'-'.now()->format('YmdHis').'-'.$employeeId,
            default => $this->sequentialNumber($model, $column, $documentType, $documentDate->format('Y'), $prefix.'-'.$documentDate->format('Y')),
        };
    }

    private function sequentialNumber(Model $model, string $column, string $documentType, string $periodKey, string $basePrefix): string
    {
        return DB::transaction(function () use ($model, $column, $documentType, $periodKey, $basePrefix) {
            $sequence = NumberSequence::query()
                ->where('document_type', $documentType)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = NumberSequence::create([
                    'document_type' => $documentType,
                    'period_key' => $periodKey,
                    'next_number' => 1,
                ]);
            }

            do {
                $number = $basePrefix.'-'.str_pad((string) $sequence->next_number, 5, '0', STR_PAD_LEFT);
                $sequence->next_number++;
            } while ($model->newQuery()->where($column, $number)->exists());

            $sequence->save();

            return $number;
        });
    }

    private function uniqueDateNumber(Model $model, string $column, string $baseNumber): string
    {
        if (! $model->newQuery()->where($column, $baseNumber)->exists()) {
            return $baseNumber;
        }

        $sequence = 2;

        do {
            $number = $baseNumber.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while ($model->newQuery()->where($column, $number)->exists());

        return $number;
    }
}
