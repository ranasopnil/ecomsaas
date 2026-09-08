<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A plan change that cannot happen, said in words a shopkeeper can act on.
 *
 * Nothing is half-written when this is thrown: the shop keeps the plan it had
 * and nothing of its own is touched.
 */
class PlanChangeRefused extends RuntimeException
{
    /**
     * @param  array<int, array{label: string, used: int, allowance: int}>  $over
     */
    public function __construct(string $message, public readonly array $over = [])
    {
        parent::__construct($message);
    }

    /**
     * @param  array<int, array{label: string, used: int, allowance: int}>  $over
     */
    public static function tooMuchInTheShop(string $plan, array $over): self
    {
        $said = collect($over)
            ->map(fn (array $row) => mb_strtolower($row['label']).': you have '
                .number_format($row['used']).', '.$plan.' allows '.number_format($row['allowance']))
            ->join('; ', ' and ');

        return new self(
            'Your shop is bigger than '.$plan.' allows — '.$said
            .'. Bring those down first, or stay on your plan.',
            $over,
        );
    }

    public static function notPricedHere(string $plan, string $currency): self
    {
        return new self($plan.' is not sold in '.$currency.' yet. Ask us and we will price it.');
    }

    public static function alreadyOnIt(string $plan): self
    {
        return new self('You are already on '.$plan.'.');
    }

    public static function nothingToPayFor(): self
    {
        return new self('There is no subscription to change. Ask us to set one up.');
    }
}
