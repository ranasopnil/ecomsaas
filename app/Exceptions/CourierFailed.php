<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A courier refused something or could not be reached.
 *
 * The message is for a shopkeeper to act on, and never contains a key, a
 * token or a password — those must not reach a log, a screen or an error
 * tracker.
 */
class CourierFailed extends RuntimeException
{
    public function __construct(string $message, public readonly string $courier = '')
    {
        parent::__construct($message);
    }

    public static function unreachable(string $courier): self
    {
        return new self('Could not reach '.$courier.'. Nothing was booked. Try again in a moment.', $courier);
    }

    public static function refused(string $courier, string $message): self
    {
        return new self($message === '' ? $courier.' refused the request.' : $message, $courier);
    }

    public static function notConfigured(string $courier): self
    {
        return new self($courier.' has not been set up for this shop.', $courier);
    }

    public static function needsChoosing(string $label): self
    {
        return new self('Choose the '.mb_strtolower($label).' before handing the parcel over.');
    }
}
