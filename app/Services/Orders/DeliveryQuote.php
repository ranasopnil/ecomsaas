<?php

namespace App\Services\Orders;

use App\Facades\Tenancy;
use App\Models\DeliveryArea;
use App\Services\Storefront\BasketLine;
use App\Support\GeoPoint;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * What delivery costs for one basket going to one place.
 *
 * A shop sets a charge on each area it delivers to, because reaching Uttara
 * costs more than reaching the next street. One order pays one charge — the
 * charge for the area it is going to — never a charge per item, which is how
 * a basket of ten cheap things ends up costing more to deliver than it does
 * to buy.
 *
 * A product may override that: zero on a product means it is delivered free,
 * and a charge on a product is what that product costs to send. Where a
 * basket mixes them, the order pays the largest single charge in it, so a
 * shop is never out of pocket and a customer is never charged twice.
 */
class DeliveryQuote
{
    /**
     * @param  Collection<int, BasketLine>  $lines
     */
    public function for(Collection $lines, ?DeliveryArea $area): Money
    {
        $shop = Tenancy::current();
        $free = Money::zero($shop->currency, $shop->currency_exponent);

        if ($lines->isEmpty()) {
            return $free;
        }

        $charges = [];
        $anyUsesTheShopsCharge = false;

        foreach ($lines as $line) {
            $own = $line->product->shipping_charge_minor;

            if ($own === null) {
                $anyUsesTheShopsCharge = true;
            } else {
                $charges[] = (int) $own;
            }
        }

        if ($anyUsesTheShopsCharge) {
            $charges[] = (int) ($area?->delivery_charge_minor ?? 0);
        }

        return new Money(max($charges) ?: 0, $shop->currency, $shop->currency_exponent);
    }

    /**
     * The two numbers the checkout page needs to work the charge out for
     * itself as the customer picks a different area.
     *
     * @param  Collection<int, BasketLine>  $lines
     * @return array{floor: int, uses_shop_charge: bool}
     */
    public function breakdown(Collection $lines): array
    {
        $floor = 0;
        $usesShopCharge = false;

        foreach ($lines as $line) {
            $own = $line->product->shipping_charge_minor;

            if ($own === null) {
                $usesShopCharge = true;
            } else {
                $floor = max($floor, (int) $own);
            }
        }

        return ['floor' => $floor, 'uses_shop_charge' => $usesShopCharge];
    }

    /**
     * The area an order is going to.
     *
     * A customer whose browser told us where they are is matched to an area.
     * Where several reach them — a city area and a neighbourhood inside it —
     * the cheapest wins, because both are true and one is kinder.
     */
    public function areaFor(?GeoPoint $at): ?DeliveryArea
    {
        $areas = DeliveryArea::orderBy('position')->orderBy('id')->get();

        if ($areas->isEmpty() || $at === null) {
            return null;
        }

        return $areas
            ->filter(fn (DeliveryArea $area) => $area->reaches($at))
            ->sortBy('delivery_charge_minor')
            ->first();
    }

    /**
     * Every area a customer may pick from at checkout, cheapest first.
     *
     * @return Collection<int, DeliveryArea>
     */
    public function choices(): Collection
    {
        return DeliveryArea::orderBy('position')->orderBy('id')->get();
    }
}
