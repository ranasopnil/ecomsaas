<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the tenant (store) the current request or job belongs to.
 *
 * Nothing that touches tenant-owned data may run without a tenant bound here.
 * See App\Models\Concerns\BelongsToTenant.
 */
class Tenancy
{
    protected ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Run a callback as one tenant, then restore whatever was bound before.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }

    /**
     * Run a callback with no tenant bound. Anything touching tenant-owned
     * data inside will throw, which is the point.
     */
    public function runWithout(callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = null;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
