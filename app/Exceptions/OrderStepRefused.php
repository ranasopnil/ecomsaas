<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * A step an order cannot take, said in words a shopkeeper can act on.
 *
 * Nothing is half-written when this is thrown: the order is left exactly as
 * it was, and no stock has moved.
 */
class OrderStepRefused extends RuntimeException
{
    public static function notNext(string $from, string $to): self
    {
        return new self(
            'An order that is '.mb_strtolower(Order::labelFor($from))
            .' cannot be moved to '.mb_strtolower(Order::labelFor($to)).'.'
        );
    }

    public static function needsReason(): self
    {
        return new self('Say why, so the order still makes sense to whoever reads it next.');
    }

    public static function needsCourier(): self
    {
        return new self('Choose which courier the parcel went to.');
    }

    public static function notCashOnDelivery(): self
    {
        return new self('This order was not a cash-on-delivery one, so there is no cash for a courier to hand over.');
    }

    public static function notDeliveredYet(): self
    {
        return new self('Cash comes in when the parcel arrives. Mark it delivered first.');
    }

    public static function noAmount(): self
    {
        return new self('Say how much the courier handed over.');
    }

    public static function moreThanOwed(): self
    {
        return new self('That is more than this order is owed. Enter what actually came in.');
    }

    public static function movedAlready(): self
    {
        return new self('Somebody moved this order while you were looking at it. Open it again.');
    }
}
