<?php

namespace App\Services\Storefront;

use App\Models\DeliveryArea;
use App\Models\Product;
use App\Support\GeoPoint;
use Illuminate\Support\Collection;

/**
 * How far a shop reaches, and whether one product reaches one customer.
 *
 * A shop names the places it delivers to. Every product goes wherever the shop
 * goes, unless it is marked as going anywhere, or is tied to particular areas.
 *
 * A shop that has named no areas at all delivers everywhere: a new shop should
 * be able to sell before it has drawn anything on a map.
 */
class DeliveryReach
{
    /**
     * Every area this shop has named.
     *
     * @return Collection<int, DeliveryArea>
     */
    public function areas(): Collection
    {
        return DeliveryArea::orderBy('position')->orderBy('id')->get();
    }

    /**
     * The areas that actually cover where the customer is.
     *
     * @return Collection<int, DeliveryArea>
     */
    public function areasReaching(?GeoPoint $customer): Collection
    {
        if ($customer === null) {
            return $this->areas();
        }

        return $this->areas()->filter(fn (DeliveryArea $area) => $area->reaches($customer))->values();
    }

    /**
     * Does the shop deliver to this customer at all?
     */
    public function shopReaches(?GeoPoint $customer): bool
    {
        // Nothing drawn means no limit, not "nowhere".
        if ($this->areas()->isEmpty()) {
            return true;
        }

        return $customer === null || $this->areasReaching($customer)->isNotEmpty();
    }

    /**
     * Does this one product reach this one customer?
     */
    public function reaches(Product $product, ?GeoPoint $customer): bool
    {
        if ($product->availability === Product::AVAILABLE_ANYWHERE) {
            return true;
        }

        // Not knowing where somebody is must never hide the shop from them.
        if ($customer === null) {
            return true;
        }

        if ($product->availability === Product::AVAILABLE_AREAS) {
            $chosen = $product->relationLoaded('deliveryAreas')
                ? $product->deliveryAreas
                : $product->deliveryAreas()->get();

            // Tied to areas but none picked: treat it as following the shop,
            // rather than hiding it from everybody.
            if ($chosen->isEmpty()) {
                return $this->shopReaches($customer);
            }

            return $chosen->contains(fn (DeliveryArea $area) => $area->reaches($customer));
        }

        return $this->shopReaches($customer);
    }

    /**
     * Said plainly, for the shopkeeper's own screens.
     */
    public function describe(Product $product): string
    {
        if ($product->availability === Product::AVAILABLE_ANYWHERE) {
            return 'Delivered anywhere';
        }

        if ($product->availability === Product::AVAILABLE_AREAS) {
            $names = $product->deliveryAreas()->orderBy('position')->pluck('name');

            if ($names->isEmpty()) {
                return 'Wherever the shop delivers — no areas picked yet';
            }

            return 'Only '.$names->join(', ', ' and ');
        }

        $areas = $this->areas();

        return $areas->isEmpty()
            ? 'Wherever the shop delivers (everywhere)'
            : 'Wherever the shop delivers — '.$areas->pluck('name')->join(', ', ' and ');
    }
}
