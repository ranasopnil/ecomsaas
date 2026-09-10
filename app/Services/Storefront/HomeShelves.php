<?php

namespace App\Services\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Support\GeoPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The rows of products a shop's front page is built from.
 *
 * First the newest things that actually reach the customer, then one row per
 * category — "Vegetables", "Rice and oil" — each showing what is in it and
 * everything under it. A category with nothing the customer can be sent is
 * left out rather than shown empty.
 */
class HomeShelves
{
    /** How many products stand in one row. */
    public const PER_SHELF = 12;

    /** How many category rows a front page shows before it gets too long. */
    public const MAX_SHELVES = 8;

    /** How many products the "for you" row shows. */
    public const PICKED = 10;

    /**
     * The newest things on sale that can be delivered to this customer.
     *
     * @return Collection<int, Product>
     */
    public function pickedFor(?GeoPoint $customer, int $howMany = self::PICKED): Collection
    {
        return $this->sellable($customer)->take($howMany)->get();
    }

    /**
     * One row per category, in the order the shopkeeper arranged them.
     *
     * @return Collection<int, array{category: Category, products: Collection<int, Product>}>
     */
    public function forCustomer(?GeoPoint $customer): Collection
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->take(self::MAX_SHELVES)
            ->get();

        return $categories
            ->map(fn (Category $category) => [
                'category' => $category,
                'products' => $this->sellable($customer)
                    ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $category->familyIds()))
                    ->take(self::PER_SHELF)
                    ->get(),
            ])
            ->filter(fn (array $shelf) => $shelf['products']->isNotEmpty())
            ->values();
    }

    /**
     * Everything on sale that reaches this customer, newest first, with the
     * prices and pictures a product card needs already loaded.
     */
    protected function sellable(?GeoPoint $customer): Builder
    {
        return Product::query()
            ->onSale()
            ->deliverableTo($customer)
            ->with(['brand', 'variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
            ->latest('published_at')
            ->orderByDesc('id');
    }
}
