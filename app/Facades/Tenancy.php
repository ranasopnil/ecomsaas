<?php

namespace App\Facades;

use App\Models\Tenant;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(?Tenant $tenant)
 * @method static Tenant|null current()
 * @method static int|null id()
 * @method static bool check()
 * @method static void forget()
 * @method static mixed run(Tenant $tenant, callable $callback)
 * @method static mixed runWithout(callable $callback)
 *
 * @see \App\Support\Tenancy
 */
class Tenancy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Support\Tenancy::class;
    }
}
