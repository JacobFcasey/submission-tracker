<?php

namespace App\Models\Concerns;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model): void {
            if (! empty($model->tenant_id)) {
                return;
            }

            /** @var TenantContext $tenantContext */
            $tenantContext = app(TenantContext::class);
            $tenantId = $tenantContext->tenantId();

            if ($tenantId) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query;
        }

        return $query->where($query->getModel()->getTable() . '.tenant_id', $tenantId);
    }
}

