<?php

namespace App\Models\Concerns;

use App\Exceptions\CrossTenantWrite;
use App\Exceptions\TenantContextMissing;
use App\Facades\Tenancy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Put this on every table a merchant owns.
 *
 * It scopes all reads to the bound store, stamps tenant_id on create, and
 * refuses to move a record between stores.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $current = Tenancy::id();

            if ($current === null) {
                throw TenantContextMissing::for($model::class);
            }

            if ($model->tenant_id === null) {
                $model->tenant_id = $current;

                return;
            }

            if ((int) $model->tenant_id !== $current) {
                throw CrossTenantWrite::for($model::class, (int) $model->tenant_id, $current);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw CrossTenantWrite::for(
                    $model::class,
                    (int) $model->tenant_id,
                    (int) $model->getOriginal('tenant_id'),
                );
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
