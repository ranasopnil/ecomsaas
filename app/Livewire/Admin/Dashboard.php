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

    /** Whether this person has put the setup steps away. */
    public bool $setupHidden = false;

    public function mount(): void
    {
        $this->setupHidden = (bool) auth()->user()?->prefers('setup_hidden', false);
    }

    public function hideSetup(): void
    {
        $this->setupHidden = true;

        auth()->user()?->setPreference('setup_hidden', true);

        $this->dispatch('toast', ['text' => 'Setup steps put away. Bring them back any time.', 'tone' => 'ok']);
    }

    public function showSetup(): void
    {
        $this->setupHidden = false;

        auth()->user()?->setPreference('setup_hidden', false);
    }

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
            'sales' => $this->sales($store),
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
     * What the shop has sold, taken from stock that went out marked as sold.
     *
     * Until there is a checkout, this is the truest figure the shop has: every
     * sale recorded on the stock screen counts. Value is worked out at today's
     * prices, because a price is not written down against a stock movement.
     *
     * @return array{
     *     today: array{revenue: Money, profit: Money, units: int},
     *     yesterday: array{revenue: Money, profit: Money, units: int},
     *     change: float|null,
     *     spark: array<int, int>,
     *     sparkPeak: int,
     * }
     */
    protected function sales(Tenant $store): array
    {
        $timezone = $store->timezone;
        $startOfToday = Carbon::now($timezone)->startOfDay();

        $today = $this->soldBetween($startOfToday, Carbon::now($timezone), $store);
        $yesterday = $this->soldBetween($startOfToday->copy()->subDay(), $startOfToday, $store);

        $change = $yesterday['revenue']->minor > 0
            ? round(($today['revenue']->minor - $yesterday['revenue']->minor) / $yesterday['revenue']->minor * 100, 1)
            : null;

        return [
            'today' => $today,
            'yesterday' => $yesterday,
            'change' => $change,
            'spark' => $spark = $this->soldPerDay($timezone, 7),
            'sparkPeak' => max(1, max($spark)),
        ];
    }

    /**
     * @return array{revenue: Money, profit: Money, units: int}
     */
    protected function soldBetween(Carbon $from, Carbon $to, Tenant $store): array
    {
        $totals = InventoryMovement::query()
            ->join('product_variants', function ($join) {
                $join->on('product_variants.id', '=', 'inventory_movements.product_variant_id')
                    ->on('product_variants.tenant_id', '=', 'inventory_movements.tenant_id');
            })
            ->where('inventory_movements.reason', InventoryMovement::REASON_SOLD)
            ->whereBetween('inventory_movements.created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->selectRaw('COALESCE(SUM(ABS(inventory_movements.quantity_change)), 0) AS units')
            ->selectRaw('COALESCE(SUM(ABS(inventory_movements.quantity_change) * product_variants.price_minor), 0) AS revenue_minor')
            ->selectRaw('COALESCE(SUM(ABS(inventory_movements.quantity_change)
                         * (product_variants.price_minor - product_variants.cost_price_minor))
                         FILTER (WHERE product_variants.cost_price_minor IS NOT NULL), 0) AS profit_minor')
            ->first();

        return [
            'units' => (int) ($totals->units ?? 0),
            'revenue' => new Money((int) ($totals->revenue_minor ?? 0), $store->currency, $store->currency_exponent),
            'profit' => new Money((int) ($totals->profit_minor ?? 0), $store->currency, $store->currency_exponent),
        ];
    }

    /**
     * Units sold on each of the last few days, for the little line in the box.
     *
     * @return array<int, int>
     */
    protected function soldPerDay(string $timezone, int $days): array
    {
        $rows = InventoryMovement::query()
            ->where('reason', InventoryMovement::REASON_SOLD)
            ->where('created_at', '>=', Carbon::now($timezone)->startOfDay()->subDays($days - 1)->utc())
            ->selectRaw("(created_at AT TIME ZONE 'UTC' AT TIME ZONE ?)::date AS on_day", [$timezone])
            ->selectRaw('COALESCE(SUM(ABS(quantity_change)), 0) AS units')
            ->groupBy('on_day')
            ->pluck('units', 'on_day');

        $series = [];

        for ($back = $days - 1; $back >= 0; $back--) {
            $day = Carbon::now($timezone)->startOfDay()->subDays($back)->toDateString();
            $series[] = (int) ($rows[$day] ?? 0);
        }

        return $series;
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
            ['label' => 'Shop open', 'done' => $store->isActive(), 'icon' => 'shop',
                'hint' => 'Your address answers to customers.',
                'todo' => 'Your shop address is not answering.', 'route' => null],
            ['label' => 'First product', 'done' => $productCount > 0, 'icon' => 'box',
                'hint' => 'You have something to sell.',
                'todo' => 'Add the first thing you sell.', 'route' => 'admin.products.create'],
            ['label' => 'On sale', 'done' => $published, 'icon' => 'eye',
                'hint' => 'Customers can see it.',
                'todo' => 'Publish a product so people can buy it.', 'route' => 'admin.products.index'],
            ['label' => 'Photos', 'done' => $hasPhoto, 'icon' => 'camera',
                'hint' => 'Your products have pictures.',
                'todo' => 'Add a photo. Products with one sell far better.', 'route' => 'admin.products.index'],
            ['label' => 'Categories', 'done' => $hasCategory, 'icon' => 'grid',
                'hint' => 'Your shop is arranged.',
                'todo' => 'Group your products so people can find them.', 'route' => 'admin.categories.index'],
            ['label' => 'Costs', 'done' => $withCost, 'icon' => 'coin',
                'hint' => 'Profit is being worked out.',
                'todo' => 'Enter what you paid, to see your profit.', 'route' => 'admin.products.index'],
            ['label' => 'Own domain', 'done' => $customDomain, 'icon' => 'globe',
                'hint' => 'Your own web address points here.',
                'todo' => 'Point your own web address at the shop.', 'route' => 'admin.domains.index'],
        ];

        $done = count(array_filter($tasks, fn ($task) => $task['done']));

        // The first thing not done is what to nudge towards.
        $next = null;

        foreach ($tasks as $index => $task) {
            if (! $task['done']) {
                $next = $index;
                break;
            }
        }

        return [
            'percent' => (int) round($done / count($tasks) * 100),
            'done' => $done,
            'total' => count($tasks),
            'next' => $next,
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
