<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, $model): void
    {
        if (! app()->bound('currentCompany')) {
            return;
        }

        $company = app('currentCompany');

        if ($company instanceof Company) {
            $builder->where($model->qualifyColumn('company_id'), $company->getKey());
        }
    }
}

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model): void {
            if ($model->company_id || ! app()->bound('currentCompany')) {
                return;
            }

            $company = app('currentCompany');

            if ($company instanceof Company) {
                $model->company_id = $company->getKey();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, Company|int $company): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class)
            ->where($query->getModel()->qualifyColumn('company_id'), $company instanceof Company ? $company->getKey() : $company);
    }
}
