<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model): void {
            if (! $model->company_id && app()->bound('currentCompany')) {
                $model->company_id = app('currentCompany')->getKey();
            }

            if (! $model->company_id) {
                throw new LogicException('A company must be selected before creating tenant data.');
            }
        });

        static::addGlobalScope('company', function (Builder $builder): void {
            if (app()->bound('currentCompany')) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('company_id'),
                    app('currentCompany')->getKey()
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
