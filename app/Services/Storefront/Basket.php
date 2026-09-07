<?php

namespace App\Services\Storefront;

use App\Facades\Tenancy;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * What a shopper has picked up so far.
 *
 * Kept in the session, per shop: most shoppers never sign in, and a basket
 * has to work before they do. It holds nothing but "this variant, this many".
 * Prices are read fresh from the shop every time it is shown, so a basket
 * can never hold yesterday's price, and stock is not touched at all — that
 * happens once, in the database, when an order is placed.
 */
class Basket
{
    public const MAX_PER_LINE = 99;

    public function __construct(protected Request $request) {}

    protected function key(): string
    {
        return 'basket.'.Tenancy::id();
    }

    /**
     * @return array<int, int> variant id => quantity
     */
    public function quantities(): array
    {
        $saved = $this->request->session()->get($this->key(), []);

        return is_array($saved) ? array_map('intval', $saved) : [];
    }

    protected function save(array $quantities): void
    {
        $quantities = array_filter($quantities, fn (int $quantity) => $quantity > 0);

        if ($quantities === []) {
            $this->request->session()->forget($this->key());
        } else {
            $this->request->session()->put($this->key(), $quantities);
        }
    }

    public function add(ProductVariant $variant, int $quantity = 1): void
    {
        $quantities = $this->quantities();
        $quantities[$variant->id] = min(($quantities[$variant->id] ?? 0) + max($quantity, 1), self::MAX_PER_LINE);

        $this->save($quantities);
    }

    /** Zero takes it out. */
    public function set(ProductVariant $variant, int $quantity): void
    {
        $quantities = $this->quantities();
        $quantities[$variant->id] = min(max($quantity, 0), self::MAX_PER_LINE);

        $this->save($quantities);
    }

    public function remove(int $variantId): void
    {
        $quantities = $this->quantities();
        unset($quantities[$variantId]);

        $this->save($quantities);
    }

    public function clear(): void
    {
        $this->request->session()->forget($this->key());
    }

    /** How many things, counting each one picked up. For the badge on the icon. */
    public function count(): int
    {
        return array_sum($this->quantities());
    }

    public function isEmpty(): bool
    {
        return $this->quantities() === [];
    }

    /**
     * Everything in the basket, with current prices.
     *
     * Anything the shop has since withdrawn quietly drops out, rather than
     * failing at the till.
     *
     * @return Collection<int, BasketLine>
     */
    public function lines(): Collection
    {
        $quantities = $this->quantities();

        if ($quantities === []) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->with(['product.images', 'product.variants', 'images', 'inventory'])
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->filter(fn (ProductVariant $variant) => $variant->product?->isOnSale())
            ->keyBy('id');

        // Forget anything that is no longer for sale.
        $kept = array_intersect_key($quantities, $variants->all());

        if ($kept !== $quantities) {
            $this->save($kept);
        }

        return collect($kept)
            ->map(fn (int $quantity, int $id) => new BasketLine($variants[$id], $variants[$id]->product, $quantity))
            ->values();
    }

    /** Null when there is nothing to add up. */
    public function subtotal(): ?Money
    {
        $lines = $this->lines();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->skip(1)->reduce(
            fn (Money $sum, BasketLine $line) => $sum->plus($line->total()),
            $lines->first()->total(),
        );
    }

    /**
     * Whether this variant can go in at all right now.
     */
    public static function canSell(ProductVariant $variant): bool
    {
        $stock = $variant->relationLoaded('inventory') ? $variant->inventory : $variant->inventory()->first();

        if ($stock === null || ! $stock->track_inventory || $stock->allow_backorder) {
            return true;
        }

        return $stock->available > 0;
    }
}
