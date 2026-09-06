<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_keeps_whole_units_of_the_smallest_coin(): void
    {
        $price = Money::fromDecimal('2490.50', 'BDT');

        $this->assertSame(249050, $price->minor);
        $this->assertSame('2490.50', $price->toDecimal());
    }

    public function test_a_currency_with_no_decimals_is_not_multiplied_by_a_hundred(): void
    {
        $price = Money::fromDecimal('150000', 'IDR', 0);

        $this->assertSame(150000, $price->minor);
        $this->assertSame('150000', $price->toDecimal());
    }

    public function test_a_currency_with_three_decimals_keeps_all_three(): void
    {
        $price = Money::fromDecimal('12.345', 'KWD', 3);

        $this->assertSame(12345, $price->minor);
        $this->assertSame('12.345', $price->toDecimal());
    }

    public function test_extra_decimals_are_cut_not_rounded_up(): void
    {
        $this->assertSame(1099, Money::fromDecimal('10.999', 'BDT')->minor);
    }

    public function test_amounts_add_up_exactly(): void
    {
        $total = Money::fromDecimal('0.10', 'BDT')
            ->plus(Money::fromDecimal('0.20', 'BDT'))
            ->times(3);

        $this->assertSame(90, $total->minor);
        $this->assertSame('0.90', $total->toDecimal());
    }

    public function test_two_currencies_cannot_be_mixed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('10.00', 'BDT')->plus(Money::fromDecimal('10.00', 'MYR'));
    }

    public function test_nonsense_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('1,000.00', 'BDT');
    }

    public function test_negative_amounts_survive_the_round_trip(): void
    {
        $refund = Money::fromDecimal('-45.75', 'BDT');

        $this->assertSame(-4575, $refund->minor);
        $this->assertSame('-45.75', $refund->toDecimal());
    }
}
