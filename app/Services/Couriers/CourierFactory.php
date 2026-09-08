<?php

namespace App\Services\Couriers;

use App\Exceptions\CourierFailed;
use App\Models\Courier;
use App\Services\Couriers\Contracts\CourierDriver;
use App\Services\Couriers\Drivers\Pathao;
use App\Services\Couriers\Drivers\RedX;
use App\Services\Couriers\Drivers\Steadfast;

/**
 * Turns a shop's saved courier account into something that can talk to that
 * courier.
 *
 * Every module lives in this repository. Nothing is downloaded or loaded at
 * runtime — a courier that is not listed here cannot be talked to, and is
 * used by hand instead, which works perfectly well.
 */
class CourierFactory
{
    /** @var array<string, class-string<CourierDriver>> */
    protected const DRIVERS = [
        Pathao::KEY => Pathao::class,
        RedX::KEY => RedX::class,
        Steadfast::KEY => Steadfast::class,
    ];

    public function for(Courier $courier): CourierDriver
    {
        $driver = self::DRIVERS[$courier->driver] ?? null;

        if ($driver === null) {
            throw CourierFailed::notConfigured($courier->name);
        }

        return new $driver($courier);
    }

    /**
     * Whether this is a courier we can actually talk to, as opposed to one
     * that is only a name on a list.
     */
    public function isDriven(?string $driver): bool
    {
        return $driver !== null && array_key_exists($driver, self::DRIVERS);
    }

    /**
     * Every courier module, for the shopkeeper to choose from.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalogue(): array
    {
        return array_intersect_key(config('couriers.drivers', []), self::DRIVERS);
    }
}
