<?php

namespace App\Http\Controllers\Storefront;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CustomerLocation;
use App\Services\Storefront\TemplateCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

        $current = null;
        $slug = trim((string) $request->query('category', ''));

        if ($slug !== '') {
            $current = Category::query()->with('parent')->where('slug', $slug)->first();
        }

        $onSale = fn () => Product::query()->onSale()->deliverableTo($at);

        $products = $onSale()
            ->when($current !== null, fn (Builder $q) => $q->whereHas(
                'categories',
                fn ($c) => $c->whereIn('categories.id', $this->familyOf($current)),
            ))
            ->when($wanted !== '', fn (Builder $q) => $q->where('name', 'ilike', '%'.$wanted.'%'))
            ->when($offersOnly, fn (Builder $q) => $q->whereHas('variants', $this->discounted(...)))
            ->when($freeDeliveryOnly, fn (Builder $q) => $q->where('shipping_charge_minor', 0))
            ->with(['variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
            ->latest('published_at')
            ->take(48)
            ->get();

        // Today's deals: anything on sale for less than its normal price.
        $deals = ($current === null && $wanted === '' && ! $offersOnly)
            ? $onSale()
                ->whereHas('variants', $this->discounted(...))
                ->with(['variants' => fn ($q) => $q->orderBy('id'), 'variants.inventory', 'images'])
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

    /**
     * A category and everything under it, so "Dairy" also shows what is in
     * "Dairy › Cheese".
     *
     * @return Collection<int, int>
     */
    protected function familyOf(Category $category): Collection
    {
        $ids = collect([$category->id]);
        $frontier = [$category->id];
        $depth = 0;

        while ($frontier !== [] && $depth < 5) {
            $frontier = Category::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = $ids->merge($frontier);
            $depth++;
        }

        return $ids;
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
