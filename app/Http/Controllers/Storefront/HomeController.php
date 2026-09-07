<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\DeliveryReach;
use App\Services\Storefront\MapProviders;
use App\Services\Storefront\ShopFigures;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The front page of a shop, in whichever look the shopkeeper chose.
 *
 * Everything a customer sees here is already narrowed to what can reach them.
 * A customer who has not said where they are sees the whole shop and is asked.
 */
class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        TemplateCatalogue $templates,
        CustomerLocation $location,
        MapProviders $maps,
        DeliveryReach $reach,
        ShopFigures $figures,
    ): View {
        $shop = Tenancy::current();
        $template = $templates->activeFor($shop);
        $at = $location->point();

        $wanted = trim((string) $request->query('q', ''));
        $inCategory = trim((string) $request->query('category', ''));

        $products = Product::query()
            ->onSale()
            ->deliverableTo($at)
            ->when($wanted !== '', fn ($query) => $query->where('name', 'ilike', '%'.$wanted.'%'))
            ->when($inCategory !== '', fn ($query) => $query->whereHas(
                'categories',
                fn ($category) => $category->where('slug', $inCategory),
            ))
            ->with(['variants' => fn ($q) => $q->orderBy('id'), 'images'])
            ->latest('published_at')
            ->take(24)
            ->get();

        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->take(12)
            ->get();

        return view("storefront.{$template}.home", [
            'store' => $shop,
            'template' => config("templates.{$template}"),
            'products' => $products,
            'categories' => $categories,
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'mapProvider' => $maps->forShop($shop),
            'areas' => $reach->areas(),
            'figures' => $figures->forFront(),
            'searching' => $wanted,
            // How much of the shop the customer cannot see from where they are.
            // Only counted on the plain front page: a search or a category
            // narrows things for reasons that have nothing to do with delivery.
            'hidden' => $at === null || $wanted !== '' || $inCategory !== ''
                ? 0
                : Product::query()->onSale()->count() - $products->count(),
        ]);
    }
}
