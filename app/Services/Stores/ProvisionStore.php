<?php

namespace App\Services\Stores;

use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a store and gives it its free address on our own domain.
 *
 * Used by the artisan command today and by merchant sign-up later.
 */
class ProvisionStore
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $name, ?string $slug = null, array $attributes = []): Tenant
    {
        $slug = $this->uniqueSlug($slug ?: $name);

        return DB::transaction(function () use ($name, $slug, $attributes) {
            $tenant = Tenant::create(array_merge([
                'name' => $name,
                'slug' => $slug,
                'status' => Tenant::STATUS_ACTIVE,
            ], $attributes));

            Tenancy::run($tenant, fn () => Domain::create([
                'hostname' => $slug.config('tenancy.subdomain_suffix'),
                'type' => Domain::TYPE_SUBDOMAIN,
                'is_primary' => true,
                'status' => Domain::STATUS_VERIFIED,
                'verified_at' => now(),
            ]));

            return $tenant->fresh();
        });
    }

    protected function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'store';
        $slug = $base;
        $suffix = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
