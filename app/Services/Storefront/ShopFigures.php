<?php

namespace App\Services\Storefront;

use App\Facades\Tenancy;
use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\Product;
use App\Models\VisitDay;
use Illuminate\Support\Facades\Cache;

/**
 * The few real numbers a shop can show a customer on its front page.
 *
 * Every figure here is counted from this shop's own records. Nothing is
 * rounded up, padded or invented: a shopper reading "48 items" can go and
 * count forty-eight items. A figure with nothing behind it is left out
 * rather than shown as a zero.
 */
class ShopFigures
{
    /**
     * @return array<int, array{key: string, value: string, caption: string, icon: string}>
     */
    public function forFront(): array
    {
        $shopId = Tenancy::id();

        if ($shopId === null) {
            return [];
        }

        return Cache::remember("shop-figures:{$shopId}", now()->addMinutes(5), function () {
            $items = Product::query()->onSale()->count();
            $categories = Category::query()->count();
            $areas = DeliveryArea::query()->count();
            $shoppers = (int) VisitDay::query()
                ->where('on_day', '>=', now()->subDays(30)->toDateString())
                ->sum('visitors');

            $tiles = [];

            if ($items > 0) {
                $tiles[] = [
                    'key' => 'items',
                    'value' => number_format($items),
                    'caption' => $items === 1 ? 'Item on sale' : 'Items on sale',
                    'icon' => 'bag',
                ];
            }

            if ($categories > 0) {
                $tiles[] = [
                    'key' => 'categories',
                    'value' => number_format($categories),
                    'caption' => $categories === 1 ? 'Category to browse' : 'Categories to browse',
                    'icon' => 'grid',
                ];
            }

            // No areas drawn is not "nowhere" — it is the whole map.
            $tiles[] = $areas > 0
                ? [
                    'key' => 'areas',
                    'value' => number_format($areas),
                    'caption' => $areas === 1 ? 'Delivery area' : 'Delivery areas',
                    'icon' => 'pin',
                ]
                : [
                    'key' => 'areas',
                    'value' => 'Everywhere',
                    'caption' => 'We deliver',
                    'icon' => 'pin',
                ];

            if ($shoppers > 0) {
                $tiles[] = [
                    'key' => 'shoppers',
                    'value' => number_format($shoppers),
                    'caption' => 'Shoppers this month',
                    'icon' => 'people',
                ];
            }

            // One lonely box looks like a mistake. Two or more looks deliberate.
            return count($tiles) >= 2 ? $tiles : [];
        });
    }
}
