<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static int|null limit(string $feature)
 * @method static bool allows(string $feature)
 * @method static int|null remaining(string $feature, int $currentUsage)
 * @method static void ensureCanAdd(string $feature, int $currentUsage, int $adding = 1)
 * @method static void ensureAllows(string $feature)
 * @method static array all()
 * @method static void forget(?int $tenantId = null)
 *
 * @see \App\Services\Billing\Entitlements
 */
class Entitlements extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\Billing\Entitlements::class;
    }
}
