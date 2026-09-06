<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantContextMissing;
use App\Facades\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant-owned model to the store that is bound.
 *
 * With no store bound this throws rather than returning rows, so a forgotten
 * tenant context can never quietly return another merchant's data.
 *
 * The only legitimate way past it is ->withoutGlobalScope(TenantScope::class)
 * on a deliberate system or super-admin path.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = Tenancy::id();

        if ($tenantId === null) {
            throw TenantContextMissing::for($model::class);
        }

        $builder->where($model->getTable().'.tenant_id', $tenantId);
    }
}
