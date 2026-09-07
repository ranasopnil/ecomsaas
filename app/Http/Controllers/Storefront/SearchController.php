<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\CustomerLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What to offer somebody as they type in the shop's search box.
 *
 * Only things this shop is selling, and only things that can reach where the
 * customer is: suggesting something we cannot deliver to them wastes a tap
 * and then disappoints.
 */
class SearchController extends Controller
{
    protected const SUGGESTIONS = 7;

    public function suggest(Request $request, CustomerLocation $location): JsonResponse
    {
        $wanted = trim((string) $request->query('q', ''));

        if (mb_strlen($wanted) < 2) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $matching = Product::query()
            ->onSale()
            ->deliverableTo($location->point())
            ->where('name', 'ilike', '%'.$wanted.'%');

        $total = (clone $matching)->count();

        $products = $matching
            ->with(['variants' => fn ($q) => $q->orderBy('id'), 'images'])
            // The shortest name containing what was typed is usually the thing
            // itself rather than something that merely mentions it.
            ->orderByRaw('length(name)')
            ->take(self::SUGGESTIONS)
            ->get();

        return response()->json([
            'total' => $total,
            'results' => $products->map(function (Product $product) {
                $variant = $product->defaultVariant();
                $symbol = config('currencies.'.($variant?->currency ?? 'BDT').'.symbol', '');

                return [
                    'name' => $product->name,
                    'url' => route('storefront.product', $product->slug),
                    'price' => $variant ? $symbol.$variant->price->toDisplay() : null,
                    'was' => $variant?->isDiscounted() ? $symbol.$variant->compareAtPrice->toDisplay() : null,
                    'image' => $product->primaryImage()?->thumbnailUrl(),
                ];
            })->all(),
        ]);
    }
}
