<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;
use Stringable;

/**
 * An amount of money.
 *
 * Always a whole number of the smallest unit (poisha, cents, fils) plus the
 * currency and how many decimal places that currency has. Never a float:
 * Indonesian Rupiah has no decimals, Kuwaiti Dinar has three, and treating
 * either like dollars makes prices wrong by a factor of a hundred.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public int $minor,
        public string $currency,
        public int $exponent = 2,
    ) {
        if ($exponent < 0 || $exponent > 4) {
            throw new InvalidArgumentException("Unsupported currency exponent [{$exponent}].");
        }
    }

    public static function zero(string $currency, int $exponent = 2): self
    {
        return new self(0, $currency, $exponent);
    }

    /**
     * Build from what a human typed, e.g. "1250.50".
     */
    public static function fromDecimal(string|int|float $amount, string $currency, int $exponent = 2): self
    {
        $normalised = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $normalised)) {
            throw new InvalidArgumentException("[{$amount}] is not an amount.");
        }

        [$whole, $fraction] = array_pad(explode('.', $normalised, 2), 2, '');

        $negative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '-');

        $fraction = substr(str_pad($fraction, $exponent, '0'), 0, $exponent);
        $minor = (int) ($whole.$fraction);

        return new self($negative ? -$minor : $minor, $currency, $exponent);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency, $this->exponent);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency, $this->exponent);
    }

    public function times(int $quantity): self
    {
        return new self($this->minor * $quantity, $this->currency, $this->exponent);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor
            && $this->currency === $other->currency
            && $this->exponent === $other->exponent;
    }

    /**
     * "1250.50" — for storage in text form or for a form field, never for display.
     */
    public function toDecimal(): string
    {
        if ($this->exponent === 0) {
            return (string) $this->minor;
        }

        $sign = $this->minor < 0 ? '-' : '';
        $digits = str_pad((string) abs($this->minor), $this->exponent + 1, '0', STR_PAD_LEFT);

        return $sign.substr($digits, 0, -$this->exponent).'.'.substr($digits, -$this->exponent);
    }

    /**
     * For display only, in the shopper's language.
     */
    public function format(?string $locale = null): string
    {
        $formatter = new NumberFormatter($locale ?? app()->getLocale(), NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $this->exponent);

        return $formatter->formatCurrency((float) $this->toDecimal(), $this->currency)
            ?: $this->currency.' '.$this->toDecimal();
    }

    public function jsonSerialize(): array
    {
        return [
            'minor' => $this->minor,
            'currency' => $this->currency,
            'exponent' => $this->exponent,
        ];
    }

    public function __toString(): string
    {
        return $this->currency.' '.$this->toDecimal();
    }

    protected function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency || $this->exponent !== $other->exponent) {
            throw new InvalidArgumentException("Cannot mix {$this->currency} with {$other->currency}.");
        }
    }
}
