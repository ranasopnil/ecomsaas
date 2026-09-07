<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\Tenant;
use App\Support\GeoPoint;

/**
 * Whether what a shop sells can actually reach where a customer is.
 *
 * A shop draws one area once. Every product uses it, unless that product says
 * it goes anywhere, or draws an area of its own. Nothing here guesses: a
 * product with no usable area of its own falls back to the shop's.
 */
class DeliveryAreas
{
    /**
     * The shop's own area, or null if it delivers everywhere.
     *
     * @return array{point: GeoPoint, radius: float}|null
     */
    public function shopArea(Tenant $shop): ?array
    {
        if ($shop->delivers_everywhere) {
            return null;
        }

        $point = GeoPoint::tryFrom($shop->delivery_latitude, $shop->delivery_longitude);

        if ($point === null || ! ($shop->delivery_radius_km > 0)) {
            return null;
        }

        return ['point' => $point, 'radius' => (float) $shop->delivery_radius_km];
    }

    /**
     * The area that actually applies to one product.
     *
     * @return array{point: GeoPoint, radius: float}|null null means everywhere
     */
    public function areaFor(Product $product, Tenant $shop): ?array
    {
        if ($product->availability === Product::AVAILABLE_ANYWHERE) {
            return null;
        }

        if ($product->availability === Product::AVAILABLE_AREA) {
            $point = GeoPoint::tryFrom($product->latitude, $product->longitude);

            if ($point !== null && $product->radius_km > 0) {
                return ['point' => $point, 'radius' => (float) $product->radius_km];
            }
        }

        // Either it follows the shop, or it claimed its own area and never
        // finished drawing one. Both mean: wherever the shop delivers.
        return $this->shopArea($shop);
    }

    /**
     * Does this product reach that customer?
     */
    public function reaches(Product $product, Tenant $shop, ?GeoPoint $customer): bool
    {
        $area = $this->areaFor($product, $shop);

        if ($area === null) {
            return true;
        }

        // The shop limits where it delivers, but we do not know where the
        // customer is yet. Show it rather than hide it, and ask them.
        if ($customer === null) {
            return true;
        }

        return $customer->isWithin($area['radius'], $area['point']);
    }

    /**
     * Said plainly, for the shopkeeper's own screens.
     */
    public function describe(Product $product, Tenant $shop): string
    {
        if ($product->availability === Product::AVAILABLE_ANYWHERE) {
            return 'Delivered anywhere';
        }

        $area = $this->areaFor($product, $shop);

        if ($area === null) {
            return $product->availability === Product::AVAILABLE_AREA
                ? 'Follows the shop — no area drawn yet'
                : 'Wherever the shop delivers (everywhere)';
        }

        $within = 'within '.rtrim(rtrim(number_format($area['radius'], 1), '0'), '.').' km';

        return $product->availability === Product::AVAILABLE_AREA
            ? 'Its own area, '.$within
            : 'Wherever the shop delivers, '.$within;
    }
}
