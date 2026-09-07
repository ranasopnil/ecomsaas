<?php

namespace App\Services\Payments;

use App\Exceptions\GatewayFailed;
use App\Models\PaymentMethod;
use App\Services\Payments\Contracts\OnlineGateway;
use App\Services\Payments\Gateways\Bkash;

/**
 * Turns a shop's saved account into something that can talk to that gateway.
 *
 * Every driver lives in this repository. Nothing is downloaded or loaded at
 * runtime — a gateway that is not listed here does not exist.
 */
class GatewayFactory
{
    /** @var array<string, class-string<OnlineGateway>> */
    protected const DRIVERS = [
        Bkash::KEY => Bkash::class,
    ];

    public function for(PaymentMethod $method): OnlineGateway
    {
        $driver = self::DRIVERS[$method->gateway] ?? null;

        if ($driver === null) {
            throw GatewayFailed::notConfigured($method->gateway);
        }

        return new $driver($method);
    }

    /**
     * Whether this gateway is one we can actually talk to yet, as opposed to
     * one that is only listed in the catalogue.
     */
    public function isDriven(string $gateway): bool
    {
        return array_key_exists($gateway, self::DRIVERS);
    }
}
