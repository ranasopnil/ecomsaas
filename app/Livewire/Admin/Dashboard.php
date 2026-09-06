<?php

namespace App\Livewire\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Category;
use App\Models\Domain;
use App\Models\InventoryLevel;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Which line the activity chart is showing. */
    public string $series = 'stock';

    public function setSeries(string $series): void
    {
        $this->series = in_array($series, ['stock', 'products'], true) ? $series : 'stock';
    }

    public function render()
    {
        $store = Tenancy::current();
        $productCount = Product::count();

        return view('livewire.admin.dashboard', [
            'store' => $store,
            'productCount' => $productCount,
            'onSaleCount' => Product::onSale()->count(),
            'productAllowance' => Entitlements::limit('products'),
            'productsLeft' => Entitlements::remaining('products', $productCount),
            'stockUnits' => (int) InventoryLevel::live()->sum('available'),
            'outOfStock' => InventoryLevel::live()->where('available', '<=', 0)->count(),
            'lowStock' => InventoryLevel::live()
                ->whereNotNull('low_stock_threshold')
                ->whereColumn('available', '<=', 'low_stock_threshold')
                ->where('available', '>', 0)
                ->count(),
            'money' => $this->stockMoney($store->currency, $store->currency_exponent),
            'health' => $this->health($store, $productCount),
            'chart' => $this->chart(),
            'topProducts' => $this->topProducts($store),
            'alerts' => $this->alerts(),
            'activity' => $this->recentActivity(),
        ]);
    }

    /**
     * What the stock on the shelf cost, what it would sell for, and the
     * difference. Only lines with a cost entered are counted.
     *
     * @return array{cost: Money, retail: Money, profit: Money, margin: float|null, missingCost: int, counted: int}
     */
    protected function stockMoney(string $currency, int $exponent): array
    {
        $totals = ProductVariant::query()
            ->join('inventory_levels', function ($join) {
                $join->on('inventory_levels.product_variant_id', '=', 'product_variants.id')
                    ->on('inventory_levels.tenant_id', '=', 'product_variants.tenant_id');
            })
            ->selectRaw('COALESCE(SUM(GREATEST(inventory_levels.available, 0) * product_variants.cost_price_minor)
                         FILTER (WHERE product_variants.cost_price_minor IS NOT NULL), 0) AS cost_minor')
            ->selectRaw('COALESCE(SUM(GREATEST(inventory_levels.available, 0) * product_variants.price_minor)
                         FILTER (WHERE product_variants.cost_price_minor IS NOT NULL), 0) AS retail_minor')
            ->selectRaw('COUNT(*) FILTER (WHERE product_variants.cost_price_minor IS NULL) AS missing_cost')
            ->selectRaw('COUNT(*) FILTER (WHERE product_variants.cost_price_minor IS NOT NULL) AS counted')
            ->first();

        $cost = (int) ($totals->cost_minor ?? 0);
        $retail = (int) ($totals->retail_minor ?? 0);

        return [
            'cost' => new Money($cost, $currency, $exponent),
            'retail' => new Money($retail, $currency, $exponent),
            'profit' => new Money($retail - $cost, $currency, $exponent),
            'margin' => $retail > 0 ? round(($retail - $cost) / $retail * 100, 1) : null,
            'missingCost' => (int) ($totals->missing_cost ?? 0),
            'counted' => (int) ($totals->counted ?? 0),
        ];
    }

    /**
     * How ready the shop is to trade, and what is still to do.
     *
     * @return array{percent: int, done: int, total: int, tasks: array<int, array{label: string, done: bool, hint: string, route: string|null}>}
     */
    protected function health(Tenant $store, int $productCount): array
    {
        $hasPhoto = ProductImage::query()->exists();
        $withCost = ProductVariant::whereNotNull('cost_price_minor')->exists();
        $published = Product::onSale()->exists();
        $hasCategory = Category::query()->exists();
        $customDomain = Domain::query()->where('type', Domain::TYPE_CUSTOM)
            ->where('status', Domain::STATUS_VERIFIED)->exists();

        $tasks = [
            ['label' => 'Shop is open', 'done' => $store->isActive(),
                'hint' => 'Your shop address answers to customers.', 'route' => null],
            ['label' => 'First product added', 'done' => $productCount > 0,
                'hint' => 'Add something to sell.', 'route' => 'admin.products.create'],
            ['label' => 'A product is on sale', 'done' => $published,
                'hint' => 'Publish a product so customers can see it.', 'route' => 'admin.products.index'],
            ['label' => 'Photos added', 'done' => $hasPhoto,
                'hint' => 'Products with a photo sell far better.', 'route' => 'admin.products.index'],
            ['label' => 'Categories set up', 'done' => $hasCategory,
                'hint' => 'Arrange your shop so people can find things.', 'route' => 'admin.categories.index'],
            ['label' => 'Costs entered', 'done' => $withCost,
                'hint' => 'Enter what you paid so profit can be worked out.', 'route' => 'admin.products.index'],
            ['label' => 'Your own domain', 'done' => $customDomain,
                'hint' => 'Point your own web address at the shop.', 'route' => null],
        ];

        $done = count(array_filter($tasks, fn ($task) => $task['done']));

        return [
            'percent' => (int) round($done / count($tasks) * 100),
            'done' => $done,
            'total' => count($tasks),
            'tasks' => $tasks,
        ];
    }

    /**
     * The last fortnight of activity, drawn from what actually happened.
     *
     * @return array{points: array<int, array{label: string, value: int}>, total: int, peak: int}
     */
    protected function chart(): array
    {
        $from = Carbon::today()->subDays(13);

        $rows = $this->series === 'products'
            ? Product::query()
                ->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) AS on_day, COUNT(*) AS total')
                ->groupBy('on_day')->pluck('total', 'on_day')
            : InventoryMovement::query()
                ->where('created_at', '>=', $from)
                ->selectRaw('DATE(created_at) AS on_day, COALESCE(SUM(ABS(quantity_change)), 0) AS total')
                ->groupBy('on_day')->pluck('total', 'on_day');

        $points = [];

        for ($day = $from->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            $points[] = [
                'label' => $day->format('j M'),
                'value' => (int) ($rows[$day->toDateString()] ?? 0),
            ];
        }

        return [
            'points' => $points,
            'total' => array_sum(array_column($points, 'value')),
            'peak' => max(1, max(array_column($points, 'value'))),
        ];
    }

    /**
     * What is worth the most sitting on the shelf.
     */
    protected function topProducts(Tenant $store): Collection
    {
        return ProductVariant::query()
            ->with(['product.images', 'inventory', 'optionValues'])
            ->join('inventory_levels', function ($join) {
                $join->on('inventory_levels.product_variant_id', '=', 'product_variants.id')
                    ->on('inventory_levels.tenant_id', '=', 'product_variants.tenant_id');
            })
            ->selectRaw('product_variants.*, GREATEST(inventory_levels.available, 0) * product_variants.price_minor AS shelf_value')
            ->orderByDesc('shelf_value')
            ->limit(5)
            ->get()
            ->map(fn (ProductVariant $variant) => [
                'variant' => $variant,
                'value' => new Money((int) $variant->shelf_value, $store->currency, $store->currency_exponent),
            ]);
    }

    protected function alerts(): Collection
    {
        return InventoryLevel::live()
            ->with(['variant.product'])
            ->where(fn ($query) => $query
                ->where('available', '<=', 0)
                ->orWhere(fn ($inner) => $inner
                    ->whereNotNull('low_stock_threshold')
                    ->whereColumn('available', '<=', 'low_stock_threshold')))
            ->orderBy('available')
            ->limit(5)
            ->get();
    }

    protected function recentActivity(): Collection
    {
        return InventoryMovement::query()
            ->with(['variant.product'])
            ->orderByDesc('id')
            ->limit(6)
            ->get();
    }
}
