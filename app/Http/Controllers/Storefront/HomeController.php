<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\DeliveryReach;
use App\Services\Storefront\HomeShelves;
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
 *
 * The page is built in rows: the newest things that reach them first, then one
 * row per category. Searching or opening a category puts that aside and shows
 * a plain grid of what matched, because rows of everything else would only be
 * in the way.
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
        HomeShelves $shelves,
    ): View {
        $shop = Tenancy::current();
        $template = $templates->activeFor($shop);
        $at = $location->point();

        $wanted = trim((string) $request->query('q', ''));
        $inCategory = trim((string) $request->query('category', ''));

        // Somebody looking for one thing is answered with that one thing.
        $narrowed = $wanted !== '' || $inCategory !== '';

        $products = $narrowed
            ? Product::query()
                ->onSale()
                ->deliverableTo($at)
                ->when($wanted !== '', fn ($query) => $query->where('name', 'ilike', '%'.$wanted.'%'))
                ->when($inCategory !== '', fn ($query) => $query->whereHas(
                    'categories',
                    fn ($category) => $category->where('slug', $inCategory),
                ))
                ->with(['variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
                ->latest('published_at')
                ->take(24)
                ->get()
            : $shelves->pickedFor($at);

        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->take(12)
            ->get();

        return view("storefront.{$template}.home", [
            'store' => $shop,
            'template' => config("templates.{$template}"),
            'products' => $products,
            'shelves' => $narrowed ? collect() : $shelves->forCustomer($at),
            'categories' => $categories,
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'mapProvider' => $maps->forShop($shop),
            'areas' => $reach->areas(),
            'figures' => $figures->forFront(),
            // A real thing from this shop, to show in the search box.
            'example' => Product::query()->onSale()->latest('published_at')->orderByDesc('id')->value('name'),
            'searching' => $wanted,
            // How much of the shop the customer cannot see from where they are.
            // Only counted on the plain front page: a search or a category
            // narrows things for reasons that have nothing to do with delivery.
            'hidden' => $at === null || $narrowed
                ? 0
                : Product::query()->onSale()->count() - Product::query()->onSale()->deliverableTo($at)->count(),
        ]);
    }
}
