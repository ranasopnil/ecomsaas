<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;

/**
 * One thing in the basket, with how many of it and what that comes to.
 */
final readonly class BasketLine
{
    public function __construct(
        public ProductVariant $variant,
        public Product $product,
        public int $quantity,
    ) {}

    public function unit(): Money
    {
        return $this->variant->price;
    }

    public function total(): Money
    {
        return $this->unit()->times($this->quantity);
    }

    /** "Basmati rice" or "Basmati rice — 5 kg" when the product comes in sizes. */
    public function title(): string
    {
        return $this->product->has_variants
            ? $this->product->name.' — '.$this->variant->choiceLabel()
            : $this->product->name;
    }
}
