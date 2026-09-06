<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Turns three columns — the whole-number amount, the currency and its decimal
 * places — into one Money object, and back again.
 *
 * Used as: 'price' => MoneyCast::class.':price_minor,currency,currency_exponent'
 *
 * @implements CastsAttributes<Money, Money>
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(
        protected string $minorColumn = 'amount_minor',
        protected string $currencyColumn = 'currency',
        protected string $exponentColumn = 'currency_exponent',
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if (! isset($attributes[$this->minorColumn], $attributes[$this->currencyColumn])) {
            return null;
        }

        return new Money(
            (int) $attributes[$this->minorColumn],
            (string) $attributes[$this->currencyColumn],
            (int) ($attributes[$this->exponentColumn] ?? 2),
        );
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$this->minorColumn => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("[{$key}] must be set to a Money object.");
        }

        return [
            $this->minorColumn => $value->minor,
            $this->currencyColumn => $value->currency,
            $this->exponentColumn => $value->exponent,
        ];
    }
}
