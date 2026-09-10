<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Walking round the shop: everything on sale, by category or by search,
 * already narrowed to what reaches the customer.
 */
class BrowseController extends Controller
{
    public function __invoke(Request $request, CustomerLocation $location, TemplateCatalogue $templates): View
    {
        $shop = Tenancy::current();
        $at = $location->point();

        $wanted = trim((string) $request->query('q', ''));
        $offersOnly = $request->boolean('offers');
        $freeDeliveryOnly = $request->boolean('free-delivery');

        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        // A shopper who arrives by make rather than by aisle.
        $make = trim((string) $request->query('brand', ''));
        $brand = $make !== '' ? Brand::query()->where('slug', $make)->first() : null;

        $current = null;
        $slug = trim((string) $request->query('category', ''));

        if ($slug !== '') {
            $current = Category::query()->with('parent')->where('slug', $slug)->first();
        }

        $onSale = fn () => Product::query()->onSale()->deliverableTo($at);

        $products = $onSale()
            ->when($current !== null, fn (Builder $q) => $q->whereHas(
                'categories',
                fn ($c) => $c->whereIn('categories.id', $current->familyIds()),
            ))
            ->when($brand !== null, fn (Builder $q) => $q->where('brand_id', $brand->id))
            ->when($wanted !== '', fn (Builder $q) => $q->where('name', 'ilike', '%'.$wanted.'%'))
            ->when($offersOnly, fn (Builder $q) => $q->whereHas('variants', $this->discounted(...)))
            ->when($freeDeliveryOnly, fn (Builder $q) => $q->where('shipping_charge_minor', 0))
            ->with(['brand', 'variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
            ->latest('published_at')
            ->take(48)
            ->get();

        // Today's deals: anything on sale for less than its normal price.
        $deals = ($current === null && $brand === null && $wanted === '' && ! $offersOnly)
            ? $onSale()
                ->whereHas('variants', $this->discounted(...))
                ->with(['brand', 'variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
                ->latest('published_at')
                ->take(12)
                ->get()
            : collect();

        // A real thing from this shop, so the box shows what is worth typing.
        // Settled rather than random: a search box whose example changes on
        // every reload reads as a fault.
        $example = Product::query()->onSale()->latest('published_at')->orderByDesc('id')->value('name');

        return view('storefront.browse', [
            'store' => $shop,
            'template' => config('templates.'.$templates->activeFor($shop)),
            'location' => $location,
            'searchUrl' => route('storefront.places'),
            'categories' => $categories,
            'current' => $current,
            'brand' => $brand,
            'brands' => Brand::query()
                ->where('is_active', true)
                ->whereHas('products', fn (Builder $q) => $q->onSale())
                ->withCount(['products' => fn (Builder $q) => $q->onSale()])
                ->orderBy('name')
                ->get(),
            'products' => $products,
            'deals' => $deals,
            'biggestSaving' => $this->biggestSaving(),
            'wanted' => $wanted,
            'offersOnly' => $offersOnly,
            'freeDeliveryOnly' => $freeDeliveryOnly,
            'example' => $example,
            'hasFreeDelivery' => Product::query()->onSale()->where('shipping_charge_minor', 0)->exists(),
        ]);
    }

    protected function discounted(Builder $variants): Builder
    {
        return $variants->whereNotNull('compare_at_price_minor')
            ->whereColumn('compare_at_price_minor', '>', 'price_minor');
    }

    /**
     * The largest cut on anything on sale, as a whole percentage. Zero when
     * nothing is reduced. Never rounded up.
     */
    protected function biggestSaving(): int
    {
        $saving = ProductVariant::query()
            ->whereHas('product', fn (Builder $q) => $q->onSale())
            ->whereNotNull('compare_at_price_minor')
            ->whereColumn('compare_at_price_minor', '>', 'price_minor')
            ->selectRaw('max((compare_at_price_minor - price_minor) * 100 / compare_at_price_minor) as saving')
            ->value('saving');

        return (int) floor((float) ($saving ?? 0));
    }
}
