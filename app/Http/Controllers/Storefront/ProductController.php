<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Product;
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
    public function show(string $slug): View
    {
        $product = Product::with(['variants.inventory', 'variants.optionValues', 'options.values', 'images', 'brand'])
            ->where('slug', $slug)
            ->firstOrFail();

        $isPreview = ! $product->isOnSale();

        abort_if($isPreview && ! Auth::guard('web')->check(), 404);

        return view('storefront.product', [
            'store' => Tenancy::current(),
            'product' => $product,
            'isPreview' => $isPreview,
        ]);
    }
}
