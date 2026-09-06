<?php

namespace App\Services\Stores;

use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Package;
use App\Models\Tenant;
use App\Services\Billing\SubscribeToPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates a store and gives it its free address on our own domain.
 *
 * Used by the artisan command today and by merchant sign-up later.
 */
class ProvisionStore
{
    public function __construct(protected SubscribeToPackage $subscribe) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $name, ?string $slug = null, ?Package $package = null, array $attributes = []): Tenant
    {
        $slug = $this->uniqueSlug($slug ?: $name);
        $package = $package ?? $this->defaultPackage();

        return DB::transaction(function () use ($name, $slug, $package, $attributes) {
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

            $this->subscribe->handle($tenant, $package);

            return $tenant->fresh();
        });
    }

    protected function defaultPackage(): Package
    {
        $package = Package::query()->sellable()->orderBy('price_minor')->first();

        if ($package === null) {
            throw new RuntimeException(
                'There is no package to put the store on. Run "php artisan db:seed --class=PackageSeeder" first.'
            );
        }

        return $package;
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
