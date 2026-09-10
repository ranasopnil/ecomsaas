<?php

namespace App\Livewire\Super;

use App\Models\Addon;
use App\Models\AuditLog;
use App\Models\Concerns\TenantScope;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * How the whole platform stands.
 *
 * Everything on this screen is counted from real rows: shops from `tenants`,
 * money from the subscription payments staff have confirmed, plans from the
 * subscriptions themselves. Nothing is estimated and nothing is padded — a
 * platform with one shop on it says one shop.
 *
 * Money is never added up across currencies. There are no exchange rates in
 * this system, and inventing one would be a lie in the most expensive place
 * to tell one, so each currency is counted on its own and the biggest is
 * shown first.
 *
 * Reading across every shop is what this screen is for, so the per-shop rule
 * is deliberately stepped outside of here. That is written down: once a day,
 * per member of staff, in `audit_logs`.
 */
#[Layout('layouts.super')]
#[Title('Platform overview')]
class Dashboard extends Component
{
    /** The stretch of time the comparisons and the "so far" figures use. */
    public string $window = '30';

    /** How many months the revenue chart draws. */
    public string $months = '12';

    public const WINDOWS = [
        '7' => 'Last 7 days',
        '30' => 'Last 30 days',
        '90' => 'Last 90 days',
        '365' => 'Last 12 months',
    ];

    public function setWindow(string $days): void
    {
        $this->window = array_key_exists($days, self::WINDOWS) ? $days : '30';
    }

    public function setMonths(string $months): void
    {
        $this->months = in_array($months, ['6', '12'], true) ? $months : '12';
    }

    /**
     * Every shop, as a spreadsheet.
     *
     * The one thing on this screen that leaves the building, so it is written
     * down properly rather than counted as an ordinary look.
     */
    public function export(): StreamedResponse
    {
        $shops = Tenant::query()->orderBy('id')->get();
        $plans = $this->plansByTenant($shops->pluck('id'));
        $paid = $this->paidByTenant();

        AuditLog::record('platform.exported', null, null, 'Downloaded the shop list from the overview', [
            'shops' => $shops->count(),
        ]);

        $name = 'shops-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($shops, $plans, $paid) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Shop', 'Address', 'Country', 'Status', 'Plan', 'Plan status', 'Joined', 'Paid to the platform']);

            foreach ($shops as $shop) {
                $plan = $plans->get($shop->id);
                $money = $paid->get($shop->id);

                fputcsv($out, [
                    $shop->name,
                    $shop->slug,
                    $shop->country_code,
                    $shop->status,
                    $plan?->package?->name ?? '',
                    $plan?->status ?? '',
                    $shop->created_at?->toDateString(),
                    $money === null ? '' : $money->currency.' '.$money->toDecimal(),
                ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $this->noteTheLook();

        $days = (int) $this->window;
        $from = Carbon::now()->startOfDay()->subDays($days - 1);
        $wasFrom = $from->copy()->subDays($days);
        $wasTo = $from->copy()->subSecond();

        $shops = $this->shopCounts();
        $recent = Tenant::query()->latest('id')->take(6)->get();

        return view('livewire.super.dashboard', [
            'windows' => self::WINDOWS,
            'from' => $from,
            'to' => Carbon::now(),
            'figures' => $this->figures($shops, $from, $wasFrom, $wasTo),
            'chart' => $this->revenuePerMonth((int) $this->months),
            'donut' => $this->shopsPerPlan($shops['total']),
            'countries' => $this->shopsPerCountry($shops['total']),
            'recent' => $recent,
            'owners' => $this->ownersOf($recent->pluck('id')),
            'plans' => $this->plansByTenant($recent->pluck('id')),
            'money' => $this->moneyDue($from),
            'topShops' => $this->topShops(),
            'catalogue' => $this->catalogue(),
            'health' => $this->health(),
            'activity' => $this->activity(),
        ]);
    }

    /*
     * ---------------------------------------------------------------
     * The eight figures across the top
     * ---------------------------------------------------------------
     */

    /**
     * @param  array<string, int>  $shops
     * @return array<int, array<string, mixed>>
     */
    protected function figures(array $shops, Carbon $from, Carbon $wasFrom, Carbon $wasTo): array
    {
        $joined = Tenant::query()->where('created_at', '>=', $from)->count();
        $joinedBefore = Tenant::query()->whereBetween('created_at', [$wasFrom, $wasTo])->count();

        $takenEver = $this->confirmedBetween();
        $takenNow = $this->confirmedBetween($from);
        $takenBefore = $this->confirmedBetween($wasFrom, $wasTo);

        $recurring = $this->recurring();
        $owed = $this->waitingMoney();
        $waitingCount = SubscriptionPayment::query()
            ->withoutGlobalScope(TenantScope::class)->waiting()->count();

        $packages = Package::query()->where('is_active', true)->orderBy('sort_order')->get();

        $share = fn (int $count) => $shops['total'] === 0
            ? 'no shops yet'
            : round($count / $shops['total'] * 100, 1).'% of every shop';

        return [
            [
                'key' => 'shops', 'label' => 'Total shops', 'icon' => 'shop',
                'value' => number_format($shops['total']),
                'change' => $this->change($joined, $joinedBefore),
                'note' => $joined === 0
                    ? 'none opened in this stretch'
                    : '+'.number_format($joined).' in this stretch',
            ],
            [
                'key' => 'active', 'label' => 'Open for business', 'icon' => 'tick',
                'value' => number_format($shops['active']),
                'note' => $share($shops['active']),
            ],
            [
                'key' => 'trial', 'label' => 'On a trial', 'icon' => 'clock',
                'value' => number_format($shops['trialing']),
                'note' => $share($shops['trialing']),
            ],
            [
                'key' => 'suspended', 'label' => 'Suspended', 'icon' => 'stop',
                'value' => number_format($shops['suspended']),
                'note' => $share($shops['suspended']),
            ],
            [
                'key' => 'revenue', 'label' => 'Money taken', 'icon' => 'coin',
                'money' => $takenEver,
                'change' => $this->change(
                    $this->leadingAmount($takenNow),
                    $this->leadingAmount($takenBefore),
                ),
                'note' => 'confirmed payments, all time',
            ],
            [
                'key' => 'mrr', 'label' => 'Coming in each month', 'icon' => 'repeat',
                'money' => $recurring,
                'note' => 'from shops on a plan today',
            ],
            [
                'key' => 'due', 'label' => 'Waiting to be checked', 'icon' => 'hourglass',
                'money' => $owed,
                'note' => $waitingCount === 0
                    ? 'nothing waiting'
                    : $waitingCount.' '.($waitingCount === 1 ? 'payment' : 'payments').' to look at',
            ],
            [
                'key' => 'plans', 'label' => 'Plans on sale', 'icon' => 'badge',
                'value' => number_format($packages->count()),
                'note' => $packages->isEmpty() ? 'no plan is on sale' : $packages->pluck('name')->implode(', '),
            ],
        ];
    }

    /**
     * How many shops there are, and what state they are in.
     *
     * @return array<string, int>
     */
    protected function shopCounts(): array
    {
        $byStatus = Tenant::query()
            ->selectRaw('status, COUNT(*) AS how_many')
            ->groupBy('status')
            ->pluck('how_many', 'status');

        $trialing = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', Subscription::STATUS_TRIALING)
            ->distinct()
            ->count('tenant_id');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) $byStatus->get(Tenant::STATUS_ACTIVE, 0),
            'pending' => (int) $byStatus->get(Tenant::STATUS_PENDING, 0),
            'suspended' => (int) $byStatus->get(Tenant::STATUS_SUSPENDED, 0),
            'trialing' => $trialing,
        ];
    }

    /**
     * How much bigger or smaller than the stretch before. Null when there is
     * nothing to compare against — a first month is not "up 100%".
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

    /*
     * ---------------------------------------------------------------
     * Money, one currency at a time
     * ---------------------------------------------------------------
     */

    /**
     * Payments staff have confirmed, split by the currency they came in.
     *
     * @return Collection<int, Money>
     */
    protected function confirmedBetween(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $this->asMoney(
            SubscriptionPayment::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('status', SubscriptionPayment::STATUS_CONFIRMED)
                ->when($from !== null, fn ($q) => $q->where('confirmed_at', '>=', $from))
                ->when($to !== null, fn ($q) => $q->where('confirmed_at', '<=', $to))
                ->selectRaw('currency, currency_exponent, SUM(amount_minor) AS taken')
                ->groupBy('currency', 'currency_exponent')
                ->get(),
            'taken',
        );
    }

    /**
     * What the shops on a plan today come to in a month. A yearly plan counts
     * as a twelfth of itself, which is what "each month" has to mean.
     *
     * @return Collection<int, Money>
     */
    protected function recurring(): Collection
    {
        return $this->asMoney(
            Subscription::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->selectRaw('currency, currency_exponent')
                ->selectRaw("SUM(CASE WHEN billing_period = 'yearly' THEN price_minor / 12 ELSE price_minor END) AS recurring")
                ->groupBy('currency', 'currency_exponent')
                ->get(),
            'recurring',
        );
    }

    /**
     * Money shops say they have sent that nobody has checked yet.
     *
     * @return Collection<int, Money>
     */
    protected function waitingMoney(): Collection
    {
        return $this->asMoney(
            SubscriptionPayment::query()
                ->withoutGlobalScope(TenantScope::class)
                ->waiting()
                ->selectRaw('currency, currency_exponent, SUM(amount_minor) AS taken')
                ->groupBy('currency', 'currency_exponent')
                ->get(),
            'taken',
        );
    }

    /**
     * Rows of currency + total, turned into money, biggest first.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, Money>
     */
    protected function asMoney(Collection $rows, string $column): Collection
    {
        return $rows
            ->map(fn ($row) => new Money(
                (int) $row->{$column},
                $row->currency,
                (int) $row->currency_exponent,
            ))
            ->filter(fn (Money $money) => ! $money->isZero())
            ->sortByDesc(fn (Money $money) => $money->minor)
            ->values();
    }

    /**
     * The biggest of those totals, for comparing one stretch with another.
     * Comparing across currencies would be meaningless, so this compares the
     * currency most of the money is in.
     *
     * @param  Collection<int, Money>  $money
     */
    protected function leadingAmount(Collection $money): int
    {
        return (int) ($money->first()?->minor ?? 0);
    }

    /*
     * ---------------------------------------------------------------
     * The charts
     * ---------------------------------------------------------------
     */

    /**
     * Money taken and shops signed up, month by month.
     *
     * The bars are one currency — the one most of the money arrived in.
     * Anything taken in another currency is counted in its own row above,
     * never converted and added in here.
     *
     * @return array{points: array<int, array<string, mixed>>, currency: string|null, peakMoney: int, peakShops: int, total: Money|null, signups: int}
     */
    protected function revenuePerMonth(int $months): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);

        $leading = $this->confirmedBetween()->first();
        $currency = $leading?->currency;
        $exponent = $leading?->exponent ?? 2;

        $taken = $currency === null
            ? collect()
            : SubscriptionPayment::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('status', SubscriptionPayment::STATUS_CONFIRMED)
                ->where('currency', $currency)
                ->where('confirmed_at', '>=', $start)
                ->selectRaw("to_char(confirmed_at, 'YYYY-MM') AS in_month")
                ->selectRaw('SUM(amount_minor) AS taken')
                ->groupBy('in_month')
                ->pluck('taken', 'in_month');

        $started = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('starts_at', '>=', $start)
            ->selectRaw("to_char(starts_at, 'YYYY-MM') AS in_month")
            ->selectRaw('COUNT(*) AS how_many')
            ->groupBy('in_month')
            ->pluck('how_many', 'in_month');

        $points = [];
        $total = 0;
        $signups = 0;

        for ($back = $months - 1; $back >= 0; $back--) {
            $month = Carbon::now()->startOfMonth()->subMonths($back);
            $key = $month->format('Y-m');

            $minor = (int) ($taken[$key] ?? 0);
            $shops = (int) ($started[$key] ?? 0);

            $total += $minor;
            $signups += $shops;

            $points[] = [
                'label' => $month->format('M'),
                'month' => $month->format('F Y'),
                'minor' => $minor,
                'money' => new Money($minor, $currency ?? config('app.currency', 'BDT'), $exponent),
                'shops' => $shops,
            ];
        }

        $peakMoney = max(1, max(array_column($points, 'minor')));
        $peakShops = max(1, max(array_column($points, 'shops')));

        foreach ($points as $index => $point) {
            $points[$index]['share'] = $point['minor'] / $peakMoney;
            $points[$index]['shopShare'] = $point['shops'] / $peakShops;
        }

        return [
            'points' => $points,
            'currency' => $currency,
            'peakMoney' => $peakMoney,
            'peakShops' => $peakShops,
            'total' => $currency === null ? null : new Money($total, $currency, $exponent),
            'signups' => $signups,
        ];
    }

    /**
     * Which plan every shop is on. Shops with no subscription at all are a
     * slice of their own rather than being quietly left out of the ring.
     *
     * @return array{total: int, slices: array<int, array<string, mixed>>}
     */
    protected function shopsPerPlan(int $totalShops): array
    {
        $colours = ['#2563eb', '#7c3aed', '#0ea5e9', '#14b8a6', '#f59e0b', '#ec4899'];

        $rows = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->selectRaw('package_id, COUNT(DISTINCT tenant_id) AS shops')
            ->groupBy('package_id')
            ->orderByDesc('shops')
            ->get();

        $packages = Package::withTrashed()->whereIn('id', $rows->pluck('package_id'))->get()->keyBy('id');

        $slices = [];
        $accounted = 0;

        foreach ($rows as $index => $row) {
            $count = (int) $row->shops;
            $accounted += $count;

            $slices[] = [
                'label' => $packages->get($row->package_id)?->name ?? 'Removed plan',
                'count' => $count,
                'colour' => $colours[$index % count($colours)],
            ];
        }

        $trialing = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', Subscription::STATUS_TRIALING)
            ->distinct()
            ->count('tenant_id');

        if ($trialing > 0) {
            $accounted += $trialing;
            $slices[] = ['label' => 'On a trial', 'count' => $trialing, 'colour' => '#94a3b8'];
        }

        $without = max(0, $totalShops - $accounted);

        if ($without > 0) {
            $slices[] = ['label' => 'No plan', 'count' => $without, 'colour' => '#e2e8f0'];
        }

        foreach ($slices as $index => $slice) {
            $slices[$index]['share'] = $totalShops > 0 ? $slice['count'] / $totalShops : 0.0;
        }

        return ['total' => $totalShops, 'slices' => $slices];
    }

    /**
     * Where the shops are. The country is the one on the shop's own record,
     * so this is the market it was set up for, not a guess from an address.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function shopsPerCountry(int $totalShops): array
    {
        $names = config('countries');

        return Tenant::query()
            ->selectRaw('country_code, COUNT(*) AS how_many')
            ->groupBy('country_code')
            ->orderByDesc('how_many')
            ->get()
            ->map(fn ($row) => [
                'code' => $row->country_code,
                'name' => $names[$row->country_code]['name'] ?? ($row->country_code ?: 'Not set'),
                'count' => (int) $row->how_many,
                'share' => $totalShops > 0 ? (int) $row->how_many / $totalShops : 0.0,
            ])
            ->all();
    }

    /*
     * ---------------------------------------------------------------
     * The tables
     * ---------------------------------------------------------------
     */

    /**
     * The person who owns each of these shops.
     *
     * @param  Collection<int, int>  $tenantIds
     * @return Collection<int, User>
     */
    protected function ownersOf(Collection $tenantIds): Collection
    {
        return User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $tenantIds)
            ->where('role', User::ROLE_OWNER)
            ->orderBy('id')
            ->get()
            ->keyBy('tenant_id');
    }

    /**
     * The plan each of these shops is on.
     *
     * @param  Collection<int, int>  $tenantIds
     * @return Collection<int, Subscription>
     */
    protected function plansByTenant(Collection $tenantIds): Collection
    {
        return Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $tenantIds)
            ->active()
            ->with('package')
            ->orderBy('id')
            ->get()
            ->keyBy('tenant_id');
    }

    /**
     * What each shop has actually paid the platform, all time.
     *
     * A shop that has paid in more than one currency keeps the biggest —
     * there is nothing sensible to add them into.
     *
     * @return Collection<int, Money>
     */
    protected function paidByTenant(): Collection
    {
        return SubscriptionPayment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', SubscriptionPayment::STATUS_CONFIRMED)
            ->selectRaw('tenant_id, currency, currency_exponent, SUM(amount_minor) AS taken')
            ->groupBy('tenant_id', 'currency', 'currency_exponent')
            ->get()
            ->map(fn ($row) => [
                'tenant_id' => (int) $row->tenant_id,
                'money' => new Money((int) $row->taken, $row->currency, (int) $row->currency_exponent),
            ])
            ->sortByDesc(fn (array $row) => $row['money']->minor)
            ->unique('tenant_id')
            ->mapWithKeys(fn (array $row) => [$row['tenant_id'] => $row['money']]);
    }

    /**
     * The shops that have paid the platform the most, and whether they are
     * paying more or less than they were.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function topShops(): array
    {
        $paid = $this->paidByTenant()->sortByDesc(fn (Money $money) => $money->minor)->take(5);

        if ($paid->isEmpty()) {
            return [];
        }

        $shops = Tenant::query()->whereIn('id', $paid->keys())->get()->keyBy('id');

        $recent = $this->paidBetween(Carbon::now()->subDays(30), Carbon::now());
        $before = $this->paidBetween(Carbon::now()->subDays(60), Carbon::now()->subDays(30)->subSecond());

        $rows = [];

        foreach ($paid as $tenantId => $money) {
            $shop = $shops->get($tenantId);

            if ($shop === null) {
                continue;
            }

            $rows[] = [
                'shop' => $shop,
                'money' => $money,
                'change' => $this->change(
                    (int) ($recent[$tenantId] ?? 0),
                    (int) ($before[$tenantId] ?? 0),
                ),
            ];
        }

        return $rows;
    }

    /**
     * Confirmed payments per shop between two moments, in minor units.
     *
     * @return Collection<int, int>
     */
    protected function paidBetween(Carbon $from, Carbon $to): Collection
    {
        return SubscriptionPayment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', SubscriptionPayment::STATUS_CONFIRMED)
            ->whereBetween('confirmed_at', [$from, $to])
            ->selectRaw('tenant_id, SUM(amount_minor) AS taken')
            ->groupBy('tenant_id')
            ->pluck('taken', 'tenant_id');
    }

    /*
     * ---------------------------------------------------------------
     * The panels down the bottom
     * ---------------------------------------------------------------
     */

    /**
     * The four things staff need to know about money this morning.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function moneyDue(Carbon $from): array
    {
        $payments = fn (string $status) => SubscriptionPayment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', $status);

        $waitingCount = (clone $payments(SubscriptionPayment::STATUS_CLAIMED))->count();

        $overdue = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->get();

        $rejectedCount = (clone $payments(SubscriptionPayment::STATUS_REJECTED))
            ->where('created_at', '>=', $from)->count();

        return [
            [
                'label' => 'Waiting to be checked', 'tone' => 'amber',
                'count' => $waitingCount.' '.($waitingCount === 1 ? 'payment' : 'payments'),
                'money' => $this->waitingMoney(),
            ],
            [
                'label' => 'Behind on paying', 'tone' => 'rose',
                'count' => $overdue->count().' '.($overdue->count() === 1 ? 'shop' : 'shops'),
                'money' => $this->asMoney(
                    $overdue->groupBy(fn (Subscription $s) => $s->currency.'|'.$s->currency_exponent)
                        ->map(fn ($group, $key) => (object) [
                            'currency' => explode('|', $key)[0],
                            'currency_exponent' => explode('|', $key)[1],
                            'taken' => $group->sum('price_minor'),
                        ])->values(),
                    'taken',
                ),
            ],
            [
                'label' => 'Taken in this stretch', 'tone' => 'emerald',
                'count' => 'confirmed by staff',
                'money' => $this->confirmedBetween($from),
            ],
            [
                'label' => 'Could not be found', 'tone' => 'slate',
                'count' => $rejectedCount.' '.($rejectedCount === 1 ? 'payment' : 'payments'),
                'money' => $this->asMoney(
                    (clone $payments(SubscriptionPayment::STATUS_REJECTED))
                        ->where('created_at', '>=', $from)
                        ->selectRaw('currency, currency_exponent, SUM(amount_minor) AS taken')
                        ->groupBy('currency', 'currency_exponent')
                        ->get(),
                    'taken',
                ),
            ],
        ];
    }

    /**
     * What shops can be given: add-ons, looks and ways of taking money.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function catalogue(): array
    {
        $gateways = collect(config('gateways'));

        return [
            [
                'label' => 'Add-ons', 'icon' => 'plus', 'route' => 'super.addons.index',
                'count' => Addon::query()->where('is_active', true)->count(),
                'of' => Addon::query()->count(),
                'word' => 'switched on',
            ],
            [
                'label' => 'Templates', 'icon' => 'brush', 'route' => 'super.templates.index',
                'count' => collect(config('templates'))->count(),
                'of' => collect(config('templates'))->count(),
                'word' => 'shop looks',
            ],
            [
                'label' => 'Gateways', 'icon' => 'wallet', 'route' => 'super.gateways.index',
                'count' => $gateways->filter(fn ($gateway) => ($gateway['kind'] ?? '') === 'online')->count(),
                'of' => $gateways->count(),
                'word' => 'take money online',
            ],
        ];
    }

    /**
     * How the machine underneath is doing.
     *
     * Only things that can actually be measured from here. There is no
     * uptime monitor and no API health check on this platform, so neither is
     * claimed: what is shown is how long the server has been up, how full
     * the disk is, and what is stuck in the queues.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function health(): array
    {
        $rows = [];

        $uptime = @file_get_contents('/proc/uptime');

        if ($uptime !== false && $uptime !== null) {
            $seconds = (int) (float) explode(' ', trim($uptime))[0];

            $rows[] = [
                'label' => 'Server up', 'value' => $this->plainly($seconds), 'tone' => 'ok',
            ];
        }

        $free = @disk_free_space(base_path());
        $all = @disk_total_space(base_path());

        if ($free !== false && $all !== false && $all > 0) {
            $used = (int) round(($all - $free) / $all * 100);

            $rows[] = [
                'label' => 'Disk used', 'value' => $used.'%', 'bar' => $used / 100,
                'tone' => $used >= 90 ? 'bad' : ($used >= 75 ? 'warn' : 'ok'),
            ];
        }

        $stuck = DB::table('outbox_events')->whereNull('processed_at')->count();

        $rows[] = [
            'label' => 'Waiting to be sent', 'value' => number_format($stuck),
            'tone' => $stuck > 100 ? 'warn' : 'ok',
            'note' => 'orders, emails and the like',
        ];

        $failed = DB::table('failed_jobs')->count();

        $rows[] = [
            'label' => 'Jobs that failed', 'value' => number_format($failed),
            'tone' => $failed > 0 ? 'bad' : 'ok',
        ];

        $rows[] = [
            'label' => 'Database', 'value' => 'answering',
            'tone' => 'ok',
            'note' => 'every figure here came from it',
        ];

        return $rows;
    }

    /**
     * A stretch of seconds, said the way a person would say it.
     */
    protected function plainly(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        if ($days > 0) {
            return $days.' '.($days === 1 ? 'day' : 'days').', '.$hours.'h';
        }

        return $hours > 0
            ? $hours.' '.($hours === 1 ? 'hour' : 'hours')
            : intdiv($seconds, 60).' min';
    }

    /**
     * The last few things staff did that reached across shops.
     *
     * @return Collection<int, AuditLog>
     */
    protected function activity(): Collection
    {
        return AuditLog::query()
            ->where('action', '!=', 'platform.viewed')
            ->latest('id')
            ->take(6)
            ->get();
    }

    /**
     * Write down, once a day per member of staff, that somebody read the
     * platform-wide figures. Every query on this screen deliberately steps
     * outside the per-shop rule, and the rule is that stepping outside it is
     * recorded. One row a day is a record; one row a click is noise.
     */
    protected function noteTheLook(): void
    {
        $admin = auth('admin')->user();

        if ($admin === null) {
            return;
        }

        $already = AuditLog::query()
            ->where('admin_id', $admin->id)
            ->where('action', 'platform.viewed')
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->exists();

        if (! $already) {
            AuditLog::record('platform.viewed', null, null, 'Looked at the platform overview');
        }
    }
}
