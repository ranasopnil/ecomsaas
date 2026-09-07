<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An order that cannot be placed, said in words a customer can act on.
 *
 * Nothing is half-written when this is thrown: the whole thing is rolled back,
 * so no stock is taken and no order exists.
 */
class OrderRefused extends RuntimeException
{
    public static function emptyBasket(): self
    {
        return new self('There is nothing in your basket.');
    }

    public static function outOfStock(string $name): self
    {
        return new self("Somebody bought the last {$name} while you were checking out. Take it out of your basket and try again.");
    }

    public static function cannotDeliver(): self
    {
        return new self('This shop does not deliver to where you are.');
    }

    public static function noSuchPayment(): self
    {
        return new self('That way of paying is not available right now.');
    }
}
