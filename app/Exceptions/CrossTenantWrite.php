<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a record would be saved against a store other than the bound one.
 */
class CrossTenantWrite extends RuntimeException
{
    public static function for(string $model, ?int $attempted, ?int $current): self
    {
        return new self(
            "Refusing to save [{$model}] for store [{$attempted}] while store "
            ."[{$current}] is bound."
        );
    }
}
