<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-owned data is read or written with no store bound.
 * Loud failure on purpose: a silent query here would cross tenants.
 */
class TenantContextMissing extends RuntimeException
{
    public static function for(string $model): self
    {
        return new self(
            "No store is bound, so [{$model}] cannot be queried. Wrap the work in "
            .'Tenancy::run($tenant, fn () => ...), or drop the tenant scope explicitly '
            .'on the super-admin path.'
        );
    }
}
