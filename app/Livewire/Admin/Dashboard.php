<?php

namespace App\Livewire\Admin;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Category;
use App\Models\Domain;
use App\Models\InventoryLevel;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What happened in the shop today.
 *
 * Every figure on this screen is counted from the shop's own orders. Nothing
 * is rounded up, padded or invented: a shopkeeper reading "158 orders" can go
 * to the orders screen and count a hundred and fifty-eight. A shop with no
 * orders yet is told so plainly rather than shown a hopeful zero dressed up
 * as a trend.
 */
#[Layout('layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** How far back the figures at the top reach. */
    public string $range = '7';

    /** How many days the sales chart draws. */
    public string $chartDays = '14';

    /** Whether this person has put the setup steps away. */
    public bool $setupHidden = false;

    /** The stretches of time a shopkeeper can ask for. */
    public const RANGES = [
        '1' => 'Today',
        '7' => 'Last 7 days',
        '14' => 'Last 14 days',
        '30' => 'Last 30 days',
    ];

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

    public function setRange(string $days): void
    {
        $this->range = array_key_exists($days, self::RANGES) ? $days : '7';
    }

    public function setChartDays(string $days): void
    {
        $this->chartDays = in_array($days, ['7', '14', '30'], true) ? $days : '14';
    }

    public function render()
    {
        $store = Tenancy::current();
        $timezone = $store->timezone ?: config('app.timezone');

        $days = (int) $this->range;
        $from = Carbon::now($timezone)->startOfDay()->subDays($days - 1);
        $to = Carbon::now($timezone);

        // The same stretch again, immediately before, so "up on last week"
        // means something a shopkeeper can check.
        $wasFrom = $from->copy()->subDays($days);
        $wasTo = $from->copy()->subSecond();

        return view('livewire.admin.dashboard', [
            'store' => $store,
            'ranges' => self::RANGES,
            'from' => $from,
            'to' => $to,
            'figures' => $this->figures($store, $timezone, $from, $to, $wasFrom, $wasTo, $days),
            'chart' => $this->salesPerDay($store, $timezone, (int) $this->chartDays),
            'donut' => $this->ordersByState(),
            'recentOrders' => $this->recentOrders(),
            'topProducts' => $this->topProducts($store),
            'health' => $this->health($store, Product::count()),
            'alerts' => $this->alerts(),
            'productAllowance' => Entitlements::limit('products'),
        ]);
    }

    /*
     * ---------------------------------------------------------------
     * The four figures across the top
     * ---------------------------------------------------------------
     */

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function figures(Tenant $store, string $timezone, Carbon $from, Carbon $to, Carbon $wasFrom, Carbon $wasTo, int $days): array
    {
        $now = $this->countBetween($from, $to);
        $before = $this->countBetween($wasFrom, $wasTo);

        $customersEver = (int) $this->realOrders()->distinct()->count('customer_phone');
        $products = Product::count();
        $productsBefore = $products - Product::where('created_at', '>=', $from->copy()->utc())->count();

        return [
            [
                'key' => 'sales',
                'label' => 'Total sales',
                'value' => $store->currency.' '.(new Money($now['revenue'], $store->currency, $store->currency_exponent))->toDisplay(),
                'change' => $this->change($now['revenue'], $before['revenue']),
                'spark' => $this->sparkline($timezone, $days, 'revenue'),
                'icon' => 'bag',
            ],
            [
                'key' => 'orders',
                'label' => 'Orders',
                'value' => number_format($now['orders']),
                'change' => $this->change($now['orders'], $before['orders']),
                'spark' => $this->sparkline($timezone, $days, 'orders'),
                'icon' => 'cart',
            ],
            [
                'key' => 'customers',
                'label' => 'Customers',
                'value' => number_format($customersEver),
                'change' => $this->change($now['customers'], $before['customers']),
                'spark' => $this->sparkline($timezone, $days, 'customers'),
                'icon' => 'people',
                'note' => $now['customers'] === 0
                    ? 'nobody bought in this stretch'
                    : $now['customers'].' bought in this stretch',
            ],
            [
                'key' => 'products',
                'label' => 'Products',
                'value' => number_format($products),
                'change' => $this->change($products, max(0, $productsBefore)),
                'spark' => $this->sparkline($timezone, $days, 'products'),
                'icon' => 'box',
            ],
        ];
    }

    /**
     * Money taken, orders placed and people who bought, between two moments.
     *
     * @return array{revenue: int, orders: int, customers: int}
     */
    protected function countBetween(Carbon $from, Carbon $to): array
    {
        $totals = $this->realOrders()
            ->whereRaw($this->placedAt().' BETWEEN ? AND ?', [$from->copy()->utc(), $to->copy()->utc()])
            ->selectRaw('COALESCE(SUM(total_minor), 0) AS money_taken')
            ->selectRaw('COUNT(*) AS how_many')
            ->selectRaw('COUNT(DISTINCT customer_phone) AS who_bought')
            ->first();

        return [
            'revenue' => (int) ($totals->money_taken ?? 0),
            'orders' => (int) ($totals->how_many ?? 0),
            'customers' => (int) ($totals->who_bought ?? 0),
        ];
    }

    /**
     * How much bigger or smaller than the stretch before. Null when there is
     * nothing to compare against — a first week is not "up 100%".
     *
     * @return array{percent: float, up: bool}|null
     */
    protected function change(int $now, int $before): ?array
    {
        if ($before <= 0) {
            return null;
        }

        return [
            'percent' => round(abs($now - $before) / $before * 100, 1),
            'up' => $now >= $before,
        ];
    }

    /**
     * The little line inside a figure's box.
     *
     * @return array<int, int>
     */
    protected function sparkline(string $timezone, int $days, string $what): array
    {
        $days = max($days, 7);
        $start = Carbon::now($timezone)->startOfDay()->subDays($days - 1);

        $rows = $what === 'products'
            ? Product::query()
                ->where('created_at', '>=', $start->copy()->utc())
                ->selectRaw("(created_at AT TIME ZONE 'UTC' AT TIME ZONE ?)::date AS on_day", [$timezone])
                ->selectRaw('COUNT(*) AS how_many')
                ->groupBy('on_day')->pluck('how_many', 'on_day')
            : $this->realOrders()
                ->whereRaw($this->placedAt().' >= ?', [$start->copy()->utc()])
                ->selectRaw("(({$this->placedAt()}) AT TIME ZONE 'UTC' AT TIME ZONE ?)::date AS on_day", [$timezone])
                ->selectRaw(match ($what) {
                    'revenue' => 'COALESCE(SUM(total_minor), 0) AS how_many',
                    'customers' => 'COUNT(DISTINCT customer_phone) AS how_many',
                    default => 'COUNT(*) AS how_many',
                })
                ->groupBy('on_day')->pluck('how_many', 'on_day');

        $series = [];

        for ($back = $days - 1; $back >= 0; $back--) {
            $day = Carbon::now($timezone)->startOfDay()->subDays($back)->toDateString();
            $series[] = (int) ($rows[$day] ?? 0);
        }

        return $series;
    }

    /*
     * ---------------------------------------------------------------
     * The two charts
     * ---------------------------------------------------------------
     */

    /**
     * Money taken on each of the last few days.
     *
     * @return array{points: array<int, array{label: string, day: string, minor: int, money: Money, share: float}>, total: Money, peak: int}
     */
    protected function salesPerDay(Tenant $store, string $timezone, int $days): array
    {
        $start = Carbon::now($timezone)->startOfDay()->subDays($days - 1);

        $rows = $this->realOrders()
            ->whereRaw($this->placedAt().' >= ?', [$start->copy()->utc()])
            ->selectRaw("(({$this->placedAt()}) AT TIME ZONE 'UTC' AT TIME ZONE ?)::date AS on_day", [$timezone])
            ->selectRaw('COALESCE(SUM(total_minor), 0) AS money_taken')
            ->groupBy('on_day')->pluck('money_taken', 'on_day');

        $points = [];
        $total = 0;

        for ($back = $days - 1; $back >= 0; $back--) {
            $day = Carbon::now($timezone)->startOfDay()->subDays($back);
            $minor = (int) ($rows[$day->toDateString()] ?? 0);
            $total += $minor;

            $points[] = [
                'label' => $day->format('j M'),
                'day' => $day->format('j M, Y'),
                'minor' => $minor,
                'money' => new Money($minor, $store->currency, $store->currency_exponent),
            ];
        }

        $peak = max(1, max(array_column($points, 'minor')));

        foreach ($points as $index => $point) {
            $points[$index]['share'] = $point['minor'] / $peak;
        }

        return [
            'points' => $points,
            'total' => new Money($total, $store->currency, $store->currency_exponent),
            'peak' => $peak,
        ];
    }

    /**
     * Where every order the shop has taken has got to.
     *
     * The seven states an order can be in are gathered into the four a
     * shopkeeper actually thinks in.
     *
     * @return array{total: int, slices: array<int, array{label: string, count: int, share: float, colour: string}>}
     */
    protected function ordersByState(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) AS how_many')
            ->groupBy('status')
            ->pluck('how_many', 'status');

        $buckets = [
            ['label' => 'Delivered', 'colour' => '#22c55e', 'states' => [Order::STATUS_DELIVERED]],
            ['label' => 'Processing', 'colour' => '#f5325b', 'states' => [
                Order::STATUS_APPROVED, Order::STATUS_PROCESSING, Order::STATUS_HANDED_OVER,
            ]],
            ['label' => 'Pending', 'colour' => '#fbbf24', 'states' => [
                Order::STATUS_PLACED, Order::STATUS_PENDING_PAYMENT,
            ]],
            ['label' => 'Cancelled', 'colour' => '#cbd5e1', 'states' => [
                Order::STATUS_CANCELLED, Order::STATUS_NOT_DELIVERED,
            ]],
        ];

        $slices = [];
        $total = 0;

        foreach ($buckets as $bucket) {
            $count = collect($bucket['states'])->sum(fn (string $state) => (int) $counts->get($state, 0));
            $total += $count;

            $slices[] = ['label' => $bucket['label'], 'count' => $count, 'colour' => $bucket['colour'], 'share' => 0.0];
        }

        foreach ($slices as $index => $slice) {
            $slices[$index]['share'] = $total > 0 ? $slice['count'] / $total : 0.0;
        }

        return ['total' => $total, 'slices' => $slices];
    }

    /*
     * ---------------------------------------------------------------
     * The two tables
     * ---------------------------------------------------------------
     */

    protected function recentOrders(): Collection
    {
        return Order::query()
            ->with(['lines' => fn ($q) => $q->limit(3), 'lines.product.images'])
            ->withCount('lines')
            ->latest('id')
            ->limit(5)
            ->get();
    }

    /**
     * What has actually sold, counted from the orders it sold in.
     */
    protected function topProducts(Tenant $store): Collection
    {
        $rows = OrderLine::query()
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'order_lines.order_id')
                    ->on('orders.tenant_id', '=', 'order_lines.tenant_id');
            })
            ->whereNotIn('orders.status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED])
            ->whereNotNull('order_lines.product_id')
            ->selectRaw('order_lines.product_id')
            ->selectRaw('SUM(order_lines.quantity) AS sold')
            ->selectRaw('SUM(order_lines.line_total_minor) AS revenue')
            ->groupBy('order_lines.product_id')
            ->orderByDesc('sold')
            ->limit(5)
            ->get();

        $products = Product::with('images')->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'product' => $products->get($row->product_id),
            'sold' => (int) $row->sold,
            'revenue' => new Money((int) $row->revenue, $store->currency, $store->currency_exponent),
        ])->filter(fn (array $row) => $row['product'] !== null)->values();
    }

    /*
     * ---------------------------------------------------------------
     * Small things
     * ---------------------------------------------------------------
     */

    /**
     * Orders that actually count: an attempt that never became one does not.
     */
    protected function realOrders(): Builder
    {
        return Order::query()->whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED]);
    }

    /**
     * When an order happened. Placed is the truth; created stands in for the
     * rare order that has not been stamped yet.
     */
    protected function placedAt(): string
    {
        return 'COALESCE(placed_at, created_at)';
    }

    /**
     * How ready the shop is to trade, and what is still to do.
     *
     * @return array{percent: int, done: int, total: int, next: int|null, tasks: array<int, array<string, mixed>>}
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
     * Stock that has run out, or is about to.
     */
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
            ->limit(4)
            ->get();
    }
}
