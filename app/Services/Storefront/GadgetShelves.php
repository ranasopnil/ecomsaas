<?php

namespace App\Services\Storefront;

use App\Models\Brand;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StorefrontFooter;
use App\Support\GeoPoint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The extra rows an electronics shop front is built from.
 *
 * A gadget shop is shopped differently from a grocery: people arrive knowing
 * the brand, or looking for what is discounted, or wanting whatever is newest.
 * So this adds three rows the other templates have no use for — what has
 * actually sold most, what is genuinely reduced, and which makes this shop
 * stocks — each counted from this shop's own records.
 *
 * Nothing here invents a figure. A row with nothing in it is left out, so a
 * shop with no orders yet simply has no "best sellers" row rather than an
 * empty shelf with a heading over it.
 */
class GadgetShelves
{
    /** How many products stand in one row. */
    public const PER_ROW = 10;

    /** How many makes the brand strip shows. */
    public const BRANDS = 12;

    /**
     * What this shop has actually sold most of.
     *
     * Counted from the order lines it sold in, so it is the shop's real best
     * sellers and not a hand-picked list. An order that never became one does
     * not count towards it.
     *
     * @return Collection<int, Product>
     */
    public function topSelling(?GeoPoint $customer, int $howMany = self::PER_ROW): Collection
    {
        $ranked = OrderLine::query()
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'order_lines.order_id')
                    ->on('orders.tenant_id', '=', 'order_lines.tenant_id');
            })
            ->whereNotIn('orders.status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED])
            ->whereNotNull('order_lines.product_id')
            ->selectRaw('order_lines.product_id')
            ->selectRaw('SUM(order_lines.quantity) AS sold')
            ->groupBy('order_lines.product_id')
            ->orderByDesc('sold')
            ->take($howMany * 2)
            ->pluck('product_id');

        if ($ranked->isEmpty()) {
            return collect();
        }

        // Still on sale, and still something this customer can be sent.
        $products = $this->sellable($customer)
            ->whereIn('id', $ranked)
            ->get()
            ->keyBy('id');

        return $ranked
            ->map(fn (int $id) => $products->get($id))
            ->filter()
            ->take($howMany)
            ->values();
    }

    /**
     * Things actually reduced: the price asked is below the price it was.
     *
     * @return Collection<int, Product>
     */
    public function offers(?GeoPoint $customer, int $howMany = self::PER_ROW): Collection
    {
        return $this->sellable($customer)
            ->whereHas('variants', fn (Builder $q) => $q
                ->whereNotNull('compare_at_price_minor')
                ->whereColumn('compare_at_price_minor', '>', 'price_minor'))
            ->take($howMany)
            ->get();
    }

    /**
     * The makes this shop stocks, commonest first.
     *
     * Only brands with something on sale behind them — a brand strip that
     * leads to an empty page is worse than no brand strip.
     *
     * @return Collection<int, array{brand: Brand, items: int}>
     */
    public function brands(int $howMany = self::BRANDS): Collection
    {
        return Brand::query()
            ->where('is_active', true)
            ->whereHas('products', fn (Builder $q) => $q->onSale())
            ->withCount(['products' => fn (Builder $q) => $q->onSale()])
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->take($howMany)
            ->get()
            ->map(fn (Brand $brand) => ['brand' => $brand, 'items' => (int) $brand->products_count]);
    }

    /**
     * The short strip of promises across the top.
     *
     * Every one of these is read from what the shopkeeper has actually set
     * up. The shop that inspired this look promises "0% EMI" and "100%
     * secure payment"; this platform cannot know either of those, so it says
     * instead which ways of paying are switched on, where the shop really
     * delivers, and how to reach a person. A promise nobody could keep is
     * left out rather than printed.
     *
     * @return array<int, array{icon: string, title: string, detail: string}>
     */
    public function promises(): array
    {
        $strip = [];

        $ways = PaymentMethod::query()->where('is_enabled', true)->orderBy('position')->get();
        $onDelivery = $ways->firstWhere('gateway', 'cod') !== null;
        $upFront = $ways->filter(fn (PaymentMethod $way) => $way->gateway !== 'cod');

        if ($onDelivery) {
            $strip[] = [
                'icon' => 'wallet',
                'title' => 'Pay on delivery',
                'detail' => 'Hand the money over when it arrives',
            ];
        }

        if ($upFront->isNotEmpty()) {
            $strip[] = [
                'icon' => 'card',
                'title' => 'Pay online',
                'detail' => $upFront->take(3)->map(fn (PaymentMethod $way) => $way->label())->implode(', '),
            ];
        }

        $areas = DeliveryArea::query()->count();

        $strip[] = [
            'icon' => 'van',
            'title' => $areas > 0 ? 'Delivered to you' : 'Delivered anywhere',
            'detail' => $areas > 0
                ? $areas.' '.($areas === 1 ? 'area' : 'areas').' we deliver to'
                : 'This shop delivers everywhere',
        ];

        $footer = StorefrontFooter::forShop();

        if (filled($footer->phone)) {
            $strip[] = [
                'icon' => 'phone',
                'title' => 'Talk to somebody',
                'detail' => $footer->phone,
            ];
        }

        // One promise on its own looks like an apology. Two or more looks
        // like a shop that has its house in order.
        return count($strip) >= 2 ? $strip : [];
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
