<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A gateway refused something or could not be reached.
 *
 * Carries two messages on purpose: one a customer may see, and one for the
 * shopkeeper's own screens. Neither ever contains a key, a secret or a
 * password — those must not reach a log, a screen or an error tracker.
 */
class GatewayFailed extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $gateway = '',
        public readonly ?string $gatewayCode = null,
        public readonly string $customerMessage = 'We could not take that payment. Please try again.',
    ) {
        parent::__construct($message);
    }

    public static function unreachable(string $gateway): self
    {
        return new self(
            "Could not reach {$gateway}.",
            $gateway,
            null,
            'The payment service is not responding. Please try again in a moment.',
        );
    }

    public static function refused(string $gateway, string $message, ?string $code = null): self
    {
        return new self(
            $message === '' ? "{$gateway} refused the request." : $message,
            $gateway,
            $code,
        );
    }

    public static function notConfigured(string $gateway): self
    {
        return new self(
            "{$gateway} has not been set up for this shop.",
            $gateway,
            null,
            'That way of paying is not available right now.',
        );
    }
}
