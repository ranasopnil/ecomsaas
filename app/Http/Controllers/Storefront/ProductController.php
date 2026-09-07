<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\Basket;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The product page a shopper sees.
 *
 * A shopkeeper who is signed in can also open a product that is not on sale
 * yet, to check it before customers can. Everybody else gets 'not found'.
 */
class ProductController extends Controller
{
    public function show(string $slug, CustomerLocation $location, TemplateCatalogue $templates): View
    {
        $product = Product::with([
            'variants.inventory', 'variants.optionValues', 'variants.images',
            'options.values', 'images', 'brand', 'categories',
        ])
            ->where('slug', $slug)
            ->firstOrFail();

        $isPreview = ! $product->isOnSale();

        abort_if($isPreview && ! Auth::guard('web')->check(), 404);

        $shop = Tenancy::current();

        return view('storefront.product', [
            'store' => $shop,
            'template' => config('templates.'.$templates->activeFor($shop)),
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'product' => $product,
            'isPreview' => $isPreview,
            'category' => $product->categories->first(),
            'alsoHere' => $this->alsoHere($product, $location),
        ]);
    }

    /**
     * A few other things from the same shop, for somebody who is not sure.
     *
     * Only what reaches this customer, and things in the same category first:
     * a shopper looking at rice is more likely to want lentils than shampoo.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    protected function alsoHere(Product $product, CustomerLocation $location)
    {
        $at = $location->point();
        $categoryIds = $product->categories->pluck('id');

        $nearby = Product::query()
            ->onSale()
            ->deliverableTo($at)
            ->whereKeyNot($product->getKey())
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas(
                'categories',
                fn ($c) => $c->whereIn('categories.id', $categoryIds),
            ))
            ->with(['variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
            ->latest('published_at')
            ->take(4)
            ->get();

        // Not enough in that category to be worth a column: fill up from the
        // rest of the shop rather than showing one lonely thing.
        if ($nearby->count() < 4) {
            $nearby = $nearby->concat(
                Product::query()
                    ->onSale()
                    ->deliverableTo($at)
                    ->whereKeyNot($product->getKey())
                    ->whereNotIn('id', $nearby->pluck('id'))
                    ->with(['variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
                    ->latest('published_at')
                    ->take(4 - $nearby->count())
                    ->get()
            );
        }

        return $nearby;
    }
}
