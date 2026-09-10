@php
    use App\Models\Order;

    $me = auth()->user();

    /* The little line inside a figure's box, drawn from its own numbers. */
    $spark = function (array $series): string {
        $peak = max(1, max($series));
        $step = count($series) > 1 ? 100 / (count($series) - 1) : 100;
        $points = [];

        foreach ($series as $index => $value) {
            $points[] = round($index * $step, 2).','.round(30 - ($value / $peak * 26), 2);
        }

        return implode(' ', $points);
    };

    $pill = fn (Order $order) => match ($order->status) {
        Order::STATUS_DELIVERED => 'bg-emerald-50 text-emerald-700',
        Order::STATUS_CANCELLED, Order::STATUS_NOT_DELIVERED => 'bg-slate-100 text-slate-500',
        Order::STATUS_PLACED, Order::STATUS_PENDING_PAYMENT => 'bg-amber-50 text-amber-700',
        default => 'bg-rose-50 text-rose-600',
    };

    $icons = [
        'bag' => 'M6 8h12l-1 12H7zM9 8a3 3 0 0 1 6 0',
        'cart' => 'M3 4h2l2.2 10.5a1.5 1.5 0 0 0 1.5 1.2h7.9a1.5 1.5 0 0 0 1.5-1.2L20 7H6M9 20h.01M17 20h.01',
        'people' => 'M15.5 20v-1.5a3.5 3.5 0 0 0-3.5-3.5H7a3.5 3.5 0 0 0-3.5 3.5V20M9.5 11.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M20.5 20v-1.5a3.5 3.5 0 0 0-2.6-3.4M15.5 4.6a3.5 3.5 0 0 1 0 6.8',
        'box' => 'M12 3 3 7.5v9L12 21l9-4.5v-9zM3 7.5 12 12l9-4.5M12 12v9',
    ];
@endphp

<div class="space-y-5">

    {{-- Welcome --}}
    <div class="welcome card rise relative overflow-hidden p-6 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-6">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    Welcome back, {{ $me?->name }}!
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">
                    Here's what's happening with your shop today.
                </p>
            </div>

            {{-- What stretch of time everything above the charts covers --}}
            <div x-data="{ open: false }" class="relative shrink-0">
                <button type="button" @click="open = ! open"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-medium hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.5" y="5" width="17" height="16" rx="2.5" /><path d="M3.5 10h17M8 3.5v3M16 3.5v3" />
                    </svg>
                    <span class="tabular-nums">
                        {{ $from->format('d M, Y') }} &ndash; {{ $to->format('d M, Y') }}
                    </span>
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute end-0 z-20 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                    @foreach ($ranges as $days => $label)
                        <button type="button" wire:click="setRange('{{ $days }}')" @click="open = false"
                                class="w-full rounded-lg px-3 py-2 text-start text-sm hover:bg-slate-50
                                       {{ $range === (string) $days ? 'font-semibold text-rose-600' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Drawn here rather than fetched, so the page never waits on a picture --}}
        <div class="pointer-events-none absolute end-40 top-0 hidden h-full items-center gap-4 xl:flex" aria-hidden="true">
            <svg class="h-24 w-32" viewBox="0 0 130 96" fill="none">
                <path d="M18 34h40l-4 50H22z" fill="#fecdd3" />
                <path d="M28 34a10 10 0 0 1 20 0" stroke="#fb7185" stroke-width="3" fill="none" stroke-linecap="round" />
                <path d="M60 44h44l-4 40H64z" fill="#fda4af" />
                <path d="M72 44a10 10 0 0 1 20 0" stroke="#f5325b" stroke-width="3" fill="none" stroke-linecap="round" />
            </svg>
            <p class="script text-2xl font-semibold leading-tight text-rose-500">
                Sell More<br>Grow Faster
            </p>
        </div>
    </div>

    {{-- The four figures --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($figures as $figure)
            <div wire:key="figure-{{ $figure['key'] }}" class="card rise rise-1 p-5">
                <div class="flex items-start gap-3">
                    <span class="stat-icon shrink-0">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $icons[$figure['icon']] }}" />
                        </svg>
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-slate-500">{{ $figure['label'] }}</p>
                        <p class="mt-0.5 truncate text-2xl font-bold tabular-nums tracking-tight">
                            {{ $figure['value'] }}
                        </p>
                    </div>
                </div>

                <div class="mt-3 flex items-end justify-between gap-3">
                    <p class="text-xs">
                        @if ($figure['change'] === null)
                            <span class="text-slate-400">
                                {{ $figure['note'] ?? 'nothing to compare with yet' }}
                            </span>
                        @else
                            <span class="font-semibold {{ $figure['change']['up'] ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $figure['change']['up'] ? '↑' : '↓' }} {{ $figure['change']['percent'] }}%
                            </span>
                            <span class="text-slate-400">from the {{ $range }} days before</span>
                        @endif
                    </p>

                    <svg class="h-8 w-20 shrink-0" viewBox="0 0 100 32" fill="none" preserveAspectRatio="none" aria-hidden="true">
                        <polyline points="{{ $spark($figure['spark']) }}"
                                  stroke="{{ ($figure['change']['up'] ?? true) ? '#f5325b' : '#94a3b8' }}"
                                  stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                    </svg>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Getting the shop ready --}}
    @php($toGo = $health['total'] - $health['done'])

    @if ($health['done'] === $health['total'])
        <div class="card rise rise-2 flex flex-wrap items-center justify-between gap-3 p-5">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m5 13 4 4 10-10" />
                    </svg>
                </span>
                <div>
                    <h2 class="font-semibold">Your shop is ready</h2>
                    <p class="text-sm text-slate-500">Everything on the setup list is done.</p>
                </div>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700">100%</span>
        </div>
    @elseif (! $setupHidden)
        <div class="card rise rise-2 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Getting started</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ $toGo }} {{ $toGo === 1 ? 'step' : 'steps' }} to go.
                    </p>
                </div>
                <button type="button" wire:click="hideSetup" class="text-sm text-slate-400 hover:text-slate-700">
                    Put away
                </button>
            </div>

            <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-rose-500" style="width: {{ max(3, $health['percent']) }}%"></div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                {{-- The one to do next, said as the thing to do --}}
                @if ($health['next'] !== null && $health['tasks'][$health['next']]['route'])
                    <a href="{{ route($health['tasks'][$health['next']]['route']) }}" wire:navigate
                       class="rounded-full bg-rose-500 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-600">
                        {{ $health['tasks'][$health['next']]['todo'] }}
                    </a>
                @endif

                @foreach ($health['tasks'] as $task)
                    @if ($task['done'])
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                            &check; {{ $task['label'] }}
                        </span>
                    @elseif ($task['route'])
                        <a href="{{ route($task['route']) }}" wire:navigate
                           class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-600">
                            {{ $task['label'] }}
                        </a>
                    @else
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500">
                            {{ $task['label'] }}
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <div class="card rise rise-2 flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
            <p class="text-sm text-slate-500">
                <span class="font-medium text-slate-700">Setting up your shop</span>
                &middot; {{ $health['done'] }} of {{ $health['total'] }} done
            </p>
            <button type="button" wire:click="showSetup" class="text-sm font-medium text-rose-600 hover:underline">
                Show the steps
            </button>
        </div>
    @endif

    {{-- Sales and orders --}}
    <div class="grid gap-5 xl:grid-cols-3">

        <div class="card rise rise-2 p-5 xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Sales overview</h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ $store->currency }} {{ $chart['total']->toDisplay() }} over these days.
                    </p>
                </div>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = ! open"
                            class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50">
                        Last {{ $chartDays }} days
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute end-0 z-20 mt-2 w-36 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                        @foreach (['7', '14', '30'] as $days)
                            <button type="button" wire:click="setChartDays('{{ $days }}')" @click="open = false"
                                    class="w-full rounded-lg px-3 py-2 text-start text-sm hover:bg-slate-50
                                           {{ $chartDays === $days ? 'font-semibold text-rose-600' : '' }}">
                                Last {{ $days }} days
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($chart['peak'] <= 1)
                <div class="py-14 text-center">
                    <p class="text-sm font-medium">Nothing sold in these days yet</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">
                        The moment an order comes in, it appears here.
                    </p>
                </div>
            @else
                <div class="mt-6 flex h-56 items-end gap-1.5 sm:gap-2">
                    @foreach ($chart['points'] as $point)
                        <div class="group relative flex h-full flex-1 flex-col justify-end">
                            <div class="bar w-full rounded-t-md bg-gradient-to-t from-rose-400 to-rose-300 transition group-hover:from-rose-500 group-hover:to-rose-400"
                                 style="height: {{ max(2, round($point['share'] * 100)) }}%; animation-delay: {{ $loop->index * 25 }}ms"></div>

                            <div class="pointer-events-none absolute bottom-full start-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-white px-3 py-2 text-xs shadow-lg ring-1 ring-slate-100 group-hover:block">
                                <span class="block text-slate-500">{{ $point['day'] }}</span>
                                <span class="block font-bold tabular-nums">
                                    {{ $store->currency }} {{ $point['money']->toDisplay() }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2 flex gap-1.5 sm:gap-2">
                    @foreach ($chart['points'] as $point)
                        <span class="flex-1 truncate text-center text-[0.65rem] text-slate-400">{{ $point['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Where every order has got to --}}
        <div class="card rise rise-3 p-5">
            <h2 class="font-semibold">Orders overview</h2>

            @if ($donut['total'] === 0)
                <div class="py-14 text-center">
                    <p class="text-sm font-medium">No orders yet</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">
                        Your first one will show up here.
                    </p>
                </div>
            @else
                <div class="mt-5 flex items-center justify-center">
                    @php($offset = 0)
                    <div class="relative">
                        <svg class="h-40 w-40 -rotate-90" viewBox="0 0 140 140">
                            <circle cx="70" cy="70" r="54" fill="none" stroke="#f1f5f9" stroke-width="18" />
                            @foreach ($donut['slices'] as $slice)
                                @if ($slice['count'] > 0)
                                    <circle cx="70" cy="70" r="54" fill="none"
                                            stroke="{{ $slice['colour'] }}" stroke-width="18"
                                            stroke-dasharray="{{ round($slice['share'] * 339.29, 2) }} 339.29"
                                            stroke-dashoffset="-{{ round($offset * 339.29, 2) }}" />
                                    @php($offset += $slice['share'])
                                @endif
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold tabular-nums">{{ number_format($donut['total']) }}</span>
                            <span class="text-xs text-slate-500">Total orders</span>
                        </div>
                    </div>
                </div>

                <ul class="mt-5 space-y-2.5">
                    @foreach ($donut['slices'] as $slice)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $slice['colour'] }}"></span>
                            <span class="flex-1 text-slate-600">{{ $slice['label'] }}</span>
                            <span class="font-semibold tabular-nums">{{ $slice['count'] }}</span>
                            <span class="w-12 text-end tabular-nums text-slate-400">
                                {{ number_format($slice['share'] * 100, 1) }}%
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Recent orders and what sells --}}
    <div class="grid gap-5 xl:grid-cols-3">

        <div class="card rise rise-3 overflow-hidden xl:col-span-2">
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <h2 class="font-semibold">Recent orders</h2>
                <a href="{{ route('admin.orders.index') }}" wire:navigate
                   class="text-sm font-medium text-rose-600 hover:underline">View all →</a>
            </div>

            @if ($recentOrders->isEmpty())
                <div class="px-5 pb-12 pt-6 text-center">
                    <p class="text-sm font-medium">No orders yet</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">
                        They appear the moment a customer checks out.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/70 text-start text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-5 py-3 text-start font-semibold">#</th>
                                <th class="px-5 py-3 text-start font-semibold">Customer</th>
                                <th class="px-5 py-3 text-start font-semibold">Products</th>
                                <th class="px-5 py-3 text-end font-semibold">Amount</th>
                                <th class="px-5 py-3 text-start font-semibold">Status</th>
                                <th class="px-5 py-3 text-start font-semibold">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentOrders as $order)
                                <tr wire:key="recent-{{ $order->id }}" class="hover:bg-slate-50/60">
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                                           class="font-semibold hover:underline">{{ $order->reference }}</a>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="font-medium">{{ $order->customer_name }}</div>
                                        <div class="text-xs text-slate-400">{{ $order->customer_phone }}</div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-1.5">
                                            @foreach ($order->lines as $line)
                                                @php($photo = $line->product?->images->first())
                                                <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200/60">
                                                    @if ($photo)
                                                        <img src="{{ $photo->thumbnailUrl() }}" alt="" loading="lazy"
                                                             class="h-full w-full object-cover">
                                                    @else
                                                        <svg class="h-4 w-4 text-slate-300" viewBox="0 0 24 24" fill="none"
                                                             stroke="currentColor" stroke-width="1.6">
                                                            <rect x="3" y="3" width="18" height="18" rx="3" />
                                                            <path d="m3 15 5-4 4 3 3-2 6 5" />
                                                        </svg>
                                                    @endif
                                                </span>
                                            @endforeach
                                            <span class="ms-1 whitespace-nowrap text-xs text-slate-500">
                                                {{ $order->lines_count }} {{ $order->lines_count === 1 ? 'item' : 'items' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-end font-semibold tabular-nums">
                                        {{ $store->currency }} {{ $order->total->toDisplay() }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="whitespace-nowrap rounded-lg px-2.5 py-1 text-xs font-medium {{ $pill($order) }}">
                                            {{ $order->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-500">
                                        {{ ($order->placed_at ?? $order->created_at)->format('d M, H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card rise rise-4 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4">
                <h2 class="font-semibold">Top selling products</h2>
                <a href="{{ route('admin.products.index') }}" wire:navigate
                   class="text-sm font-medium text-rose-600 hover:underline">View all →</a>
            </div>

            @if ($topProducts->isEmpty())
                <div class="px-5 pb-12 pt-6 text-center">
                    <p class="text-sm font-medium">Nothing has sold yet</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">
                        Once things start selling, your best will be listed here.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($topProducts as $index => $row)
                        <li wire:key="top-{{ $row['product']->id }}" class="flex items-center gap-3 px-5 py-3">
                            <span class="w-4 shrink-0 text-sm tabular-nums text-slate-400">{{ $index + 1 }}</span>

                            @php($photo = $row['product']->images->first())
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200/60">
                                @if ($photo)
                                    <img src="{{ $photo->thumbnailUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-4 w-4 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <rect x="3" y="3" width="18" height="18" rx="3" /><path d="m3 15 5-4 4 3 3-2 6 5" />
                                    </svg>
                                @endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.products.edit', $row['product']) }}" wire:navigate
                                   class="block truncate text-sm font-medium hover:underline">
                                    {{ $row['product']->name }}
                                </a>
                                <span class="text-xs text-slate-400">{{ number_format($row['sold']) }} sold</span>
                            </div>

                            <span class="shrink-0 text-sm font-semibold tabular-nums">
                                {{ $store->currency }} {{ $row['revenue']->toDisplay() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Two things worth doing next --}}
    <div class="grid gap-5 xl:grid-cols-3">
        <div class="card rise relative overflow-hidden p-6 xl:col-span-2"
             style="background: linear-gradient(100deg, #fff6f8 0%, #ffeef2 100%)">
            <div class="flex flex-wrap items-center justify-between gap-5">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-rose-500 shadow-sm">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            @if ($alerts->isNotEmpty())
                                <path d="M12 9v4M12 17h.01M10.3 4 3 17a2 2 0 0 0 1.7 3h14.6a2 2 0 0 0 1.7-3L13.7 4a2 2 0 0 0-3.4 0Z" />
                            @else
                                <path d="M6 8h12l-1 12H7zM9 8a3 3 0 0 1 6 0M12 12v5M9.5 14.5h5" />
                            @endif
                        </svg>
                    </span>
                    <div>
                        @if ($alerts->isNotEmpty())
                            <h2 class="text-lg font-bold">
                                {{ $alerts->count() }} {{ $alerts->count() === 1 ? 'thing needs' : 'things need' }} restocking
                            </h2>
                            <p class="mt-1 max-w-md text-sm text-slate-600">
                                {{ $alerts->take(2)->map(fn ($level) => $level->variant?->product?->name)->filter()->join(', ') }}
                                @if ($alerts->count() > 2) and others @endif
                                — a customer cannot buy what is not on the shelf.
                            </p>
                        @else
                            <h2 class="text-lg font-bold">Add more to your shop</h2>
                            <p class="mt-1 max-w-md text-sm text-slate-600">
                                Every photograph and every product gives a customer another reason to buy.
                                Nothing is out of stock right now.
                            </p>
                        @endif
                    </div>
                </div>

                <a href="{{ $alerts->isNotEmpty() ? route('admin.stock.index') : route('admin.products.create') }}"
                   wire:navigate class="btn btn-primary shrink-0">
                    {{ $alerts->isNotEmpty() ? 'Go to stock' : 'Add a product' }}
                </a>
            </div>
        </div>

        <div class="card rise p-6">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-500">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 13a8 8 0 0 1 16 0M4 13v4a2 2 0 0 0 2 2h1v-6H6a2 2 0 0 0-2 2ZM20 13v4a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h2 class="font-bold">Need help?</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Ask us anything about your shop.
                    </p>
                    @php($support = config('mail.from.address'))
                    @if ($support)
                        <a href="mailto:{{ $support }}?subject={{ rawurlencode('Help with '.$store->name) }}"
                           class="btn btn-quiet mt-3 !px-4 !py-2">Contact support</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
