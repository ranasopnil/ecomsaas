<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A store cannot be put on a plan that has no price in the store's currency.
 * Guessing a price, or converting one, would be inventing money.
 */
class PlanNotPricedInCurrency extends RuntimeException
{
    public static function make(string $package, string $currency): self
    {
        return new self(
            "The [{$package}] plan has no price in {$currency}. Add one in the super admin "
            .'before putting a store on it.'
        );
    }
}
