@php
    use App\Models\Subscription;
    use App\Models\Tenant;
    use App\Support\Money;
    use Illuminate\Support\Collection;

    /* The big figure in a money box: the biggest currency, spelled out. */
    $big = function (Collection $money) {
        $first = $money->first();

        return $first === null ? '—' : $first->currency.' '.$first->toDisplay();
    };

    /* Everything taken in some other currency, said plainly underneath. */
    $rest = function (Collection $money) {
        return $money->skip(1)->map(fn (Money $m) => $m->currency.' '.$m->toDisplay())->implode(' · ');
    };

    $initials = fn (?string $name) => collect(explode(' ', trim((string) $name)))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: '?';

    $shopPill = fn (Tenant $shop) => match ($shop->status) {
        Tenant::STATUS_ACTIVE => 'bg-emerald-50 text-emerald-700',
        Tenant::STATUS_SUSPENDED => 'bg-rose-50 text-rose-600',
        default => 'bg-amber-50 text-amber-700',
    };

    $shopWord = fn (Tenant $shop) => match ($shop->status) {
        Tenant::STATUS_ACTIVE => 'Open',
        Tenant::STATUS_SUSPENDED => 'Suspended',
        default => 'Waiting',
    };

    $planWord = fn (?Subscription $plan) => match ($plan?->status) {
        null => 'No plan',
        Subscription::STATUS_TRIALING => 'On a trial',
        Subscription::STATUS_PAST_DUE => 'Behind on paying',
        default => $plan->package?->name ?? 'Removed plan',
    };

    $icons = [
        'shop' => 'M4 9.5 5.5 4h13L20 9.5M4 9.5h16M4 9.5v10h16v-10M9.5 19.5v-5h5v5',
        'tick' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM8.5 12.2l2.4 2.4 4.6-5',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7.5V12l3 1.8',
        'stop' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM8.5 8.5h7v7h-7z',
        'coin' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v10M14.5 9.5a2.5 2.5 0 0 0-5 .4c0 2.6 5 1.3 5 3.9a2.5 2.5 0 0 1-5 .3',
        'repeat' => 'M4 9.5A5.5 5.5 0 0 1 9.5 4H18M18 4l-3-3M18 4l-3 3M20 14.5A5.5 5.5 0 0 1 14.5 20H6M6 20l3 3M6 20l3-3',
        'hourglass' => 'M7 3h10M7 21h10M8 3v3.5c0 2 4 3.5 4 5.5s-4 3.5-4 5.5V21M16 3v3.5c0 2-4 3.5-4 5.5s4 3.5 4 5.5V21',
        'badge' => 'M12 3 4 6.2v5.3c0 4.6 3.3 8.2 8 9.5 4.7-1.3 8-4.9 8-9.5V6.2zM9.2 12l2 2 3.6-3.8',
        'plus' => 'M4 7.5A3.5 3.5 0 0 1 7.5 4h9A3.5 3.5 0 0 1 20 7.5v9a3.5 3.5 0 0 1-3.5 3.5h-9A3.5 3.5 0 0 1 4 16.5zM12 8.5v7M8.5 12h7',
        'brush' => 'M4 20c0-2 1.5-3 3-3s3 1 3 3-1.5 2-3 2-3 0-3-2ZM10 17 20 7l-3-3L7 14',
        'wallet' => 'M3 7.5A2.5 2.5 0 0 1 5.5 5H18v3M3 7.5V18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3M3 7.5h16a2 2 0 0 1 2 2V15M17 11.5h4v3.5h-4a1.75 1.75 0 0 1 0-3.5Z',
    ];
@endphp

<div class="space-y-5">

    {{-- ------------------------------------------------------------------
         What this screen is, and the things staff do from it
    ------------------------------------------------------------------- --}}
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Platform overview</h1>
            <p class="mt-1 text-sm text-slate-500">Every shop, every plan and every payment, counted this minute.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- How far back the comparisons reach --}}
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = ! open"
                        class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-medium hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" />
                    </svg>
                    {{ $from->format('j M Y') }} – {{ $to->format('j M Y') }}
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>

                <div x-show="open" @click.outside="open = false" x-cloak
                     class="absolute end-0 z-20 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                    @foreach ($windows as $days => $label)
                        <button type="button" wire:click="setWindow('{{ $days }}')" @click="open = false"
                                class="block w-full rounded-lg px-3 py-2 text-start text-sm hover:bg-slate-50 {{ $window === (string) $days ? 'font-semibold text-blue-600' : '' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('super.stores.create') }}" wire:navigate class="btn btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Add a shop
            </a>

            <a href="{{ route('super.packages.create') }}" wire:navigate class="btn btn-quiet">Make a plan</a>

            <button type="button" wire:click="export" class="btn btn-quiet">
                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v12M8 11l4 4 4-4M4 20h16" />
                </svg>
                Download the shop list
            </button>
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         The eight figures
    ------------------------------------------------------------------- --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($figures as $figure)
            <div class="card card-hover rise rise-{{ min(4, $loop->index + 1) }} p-4">
                <div class="flex items-start justify-between gap-2">
                    <span class="plat-icon">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="{{ $icons[$figure['icon']] }}" />
                        </svg>
                    </span>

                    @if (($figure['change'] ?? null) !== null)
                        <span class="flex items-center gap-0.5 rounded-lg px-1.5 py-0.5 text-[0.7rem] font-bold
                                     {{ $figure['change']['up'] ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                            {{ $figure['change']['up'] ? '↑' : '↓' }} {{ $figure['change']['percent'] }}%
                        </span>
                    @endif
                </div>

                <p class="mt-3 text-xs font-medium text-slate-500">{{ $figure['label'] }}</p>

                @if (isset($figure['money']))
                    <p class="mt-0.5 truncate text-2xl font-bold tabular-nums" title="{{ $big($figure['money']) }}">
                        {{ $big($figure['money']) }}
                    </p>
                    @if ($rest($figure['money']) !== '')
                        <p class="mt-0.5 truncate text-[0.7rem] text-slate-400" title="{{ $rest($figure['money']) }}">
                            and {{ $rest($figure['money']) }}
                        </p>
                    @endif
                @else
                    <p class="mt-0.5 text-2xl font-bold tabular-nums">{{ $figure['value'] }}</p>
                @endif

                <p class="mt-1 truncate text-[0.7rem] text-slate-400" title="{{ $figure['note'] }}">{{ $figure['note'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------
         Money month by month, which plan shops are on, and where they are
    ------------------------------------------------------------------- --}}
    <div class="grid gap-4 xl:grid-cols-4">

        {{-- Money taken and shops signed up --}}
        <div class="card rise rise-1 p-5 xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Money and new shops</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        @if ($chart['currency'] === null)
                            No payment has been confirmed yet.
                        @else
                            {{ $chart['currency'] }} {{ $chart['total']->toDisplay() }} taken,
                            {{ number_format($chart['signups']) }} {{ Str::plural('shop', $chart['signups']) }} signed up.
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="flex items-center gap-1.5 text-[0.7rem] text-slate-500">
                        <span class="h-2.5 w-2.5 rounded-sm bg-blue-500"></span> Money
                    </span>
                    <span class="flex items-center gap-1.5 text-[0.7rem] text-slate-500">
                        <span class="h-2.5 w-2.5 rounded-full bg-violet-400"></span> New shops
                    </span>

                    <div class="flex rounded-lg bg-slate-100 p-0.5">
                        @foreach (['6', '12'] as $span)
                            <button type="button" wire:click="setMonths('{{ $span }}')"
                                    class="rounded-md px-2.5 py-1 text-[0.7rem] {{ $months === $span ? 'bg-white font-semibold text-blue-600 shadow-sm' : 'text-slate-500' }}">
                                {{ $span }} months
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($chart['currency'] === null && $chart['signups'] === 0)
                <div class="py-16 text-center">
                    <p class="text-sm font-medium">Nothing to draw yet</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">
                        The first shop to sign up, and the first payment staff confirm, both appear here.
                    </p>
                </div>
            @else
                <div class="relative mt-6">
                    {{-- The line of new shops, over the bars of money --}}
                    <div class="flex h-52 items-end gap-1.5 sm:gap-2">
                        @foreach ($chart['points'] as $point)
                            <div class="group relative flex h-full flex-1 flex-col justify-end">
                                <div class="bar w-full rounded-t-md bg-gradient-to-t from-blue-500 to-blue-400 transition group-hover:from-blue-600 group-hover:to-blue-500"
                                     style="height: {{ max(2, round($point['share'] * 100)) }}%; animation-delay: {{ $loop->index * 25 }}ms"></div>

                                {{-- Where the new-shops line passes this month --}}
                                <span class="pointer-events-none absolute start-1/2 h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 rounded-full border-2 border-white bg-violet-400"
                                      style="bottom: {{ round($point['shopShare'] * 88) + 6 }}%"></span>

                                <div class="pointer-events-none absolute bottom-full start-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-white px-3 py-2 text-xs shadow-lg ring-1 ring-slate-100 group-hover:block">
                                    <span class="block text-slate-500">{{ $point['month'] }}</span>
                                    @if ($chart['currency'] !== null)
                                        <span class="block font-bold tabular-nums">
                                            {{ $chart['currency'] }} {{ $point['money']->toDisplay() }}
                                        </span>
                                    @endif
                                    <span class="block text-slate-500">
                                        {{ $point['shops'] }} new {{ Str::plural('shop', $point['shops']) }}
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
                </div>
            @endif
        </div>

        {{-- Which plan every shop is on --}}
        <div class="card rise rise-2 p-5">
            <h2 class="font-semibold">Which plan they are on</h2>

            @if ($donut['total'] === 0)
                <div class="py-16 text-center">
                    <p class="text-sm font-medium">No shops yet</p>
                    <p class="mt-1 text-sm text-slate-500">The first one will show up here.</p>
                </div>
            @else
                <div class="mt-5 flex items-center justify-center">
                    @php($offset = 0)
                    <div class="relative">
                        <svg class="h-36 w-36 -rotate-90" viewBox="0 0 140 140">
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
                            <span class="text-xs text-slate-500">{{ Str::plural('shop', $donut['total']) }}</span>
                        </div>
                    </div>
                </div>

                <ul class="mt-5 space-y-2.5">
                    @foreach ($donut['slices'] as $slice)
                        <li class="flex items-center gap-2.5 text-sm">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $slice['colour'] }}"></span>
                            <span class="min-w-0 flex-1 truncate text-slate-600">{{ $slice['label'] }}</span>
                            <span class="font-semibold tabular-nums">{{ number_format($slice['count']) }}</span>
                            <span class="w-12 text-end text-xs tabular-nums text-slate-400">
                                {{ round($slice['share'] * 100, 1) }}%
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Where in the world they are --}}
        <div class="card rise rise-3 p-5">
            <div class="flex items-start justify-between gap-2">
                <h2 class="font-semibold">Where they are</h2>
                <a href="{{ route('super.stores.index') }}" wire:navigate
                   class="text-xs font-semibold text-blue-600 hover:text-blue-700">Every shop →</a>
            </div>

            @if ($countries === [])
                <div class="py-16 text-center">
                    <p class="text-sm font-medium">No shops yet</p>
                </div>
            @else
                <p class="mt-3 text-3xl font-bold tabular-nums">{{ number_format($donut['total']) }}</p>
                <p class="text-xs text-slate-500">across {{ count($countries) }} {{ Str::plural('country', count($countries)) }}</p>

                <ul class="mt-5 space-y-3">
                    @foreach ($countries as $country)
                        <li>
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="flex h-5 w-7 shrink-0 items-center justify-center rounded bg-slate-100 text-[0.6rem] font-bold text-slate-500">
                                        {{ $country['code'] ?: '··' }}
                                    </span>
                                    <span class="truncate text-slate-600">{{ $country['name'] }}</span>
                                </span>
                                <span class="font-semibold tabular-nums">{{ number_format($country['count']) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-500"
                                     style="width: {{ max(3, round($country['share'] * 100)) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         Who has just joined, and what money is outstanding
    ------------------------------------------------------------------- --}}
    <div class="grid gap-4 xl:grid-cols-3">

        <div class="card rise rise-1 xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold">The newest shops</h2>
                <a href="{{ route('super.stores.index') }}" wire:navigate
                   class="text-xs font-semibold text-blue-600 hover:text-blue-700">See them all →</a>
            </div>

            @if ($recent->isEmpty())
                <div class="py-16 text-center">
                    <p class="text-sm font-medium">Nobody has opened a shop yet</p>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">
                        Add the first one and it will be listed here.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-start text-xs text-slate-400">
                                <th class="px-5 py-2.5 text-start font-medium">Shop</th>
                                <th class="px-5 py-2.5 text-start font-medium">Who owns it</th>
                                <th class="px-5 py-2.5 text-start font-medium">Plan</th>
                                <th class="px-5 py-2.5 text-start font-medium">State</th>
                                <th class="px-5 py-2.5 text-start font-medium">Joined</th>
                                <th class="px-5 py-2.5 text-end font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recent as $shop)
                                @php($owner = $owners->get($shop->id))
                                @php($plan = $plans->get($shop->id))
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xs font-bold text-blue-600">
                                                {{ $initials($shop->name) }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium">{{ $shop->name }}</span>
                                                <span class="block truncate text-xs text-slate-400">{{ $shop->slug }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($owner === null)
                                            <span class="text-xs text-slate-400">Nobody signed in yet</span>
                                        @else
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[0.65rem] font-bold text-slate-500">
                                                    {{ $initials($owner->name) }}
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate">{{ $owner->name }}</span>
                                                    <span class="block truncate text-xs text-slate-400">{{ $owner->email }}</span>
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-block rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                                            {{ $planWord($plan) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-block rounded-lg px-2 py-1 text-xs font-semibold {{ $shopPill($shop) }}">
                                            {{ $shopWord($shop) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-500">
                                        {{ $shop->created_at?->format('j M Y') }}
                                    </td>
                                    <td class="px-5 py-3 text-end">
                                        <a href="{{ route('super.stores.payments', $shop) }}" wire:navigate
                                           class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Money staff need to do something about --}}
        <div class="card rise rise-2 p-5">
            <div class="flex items-start justify-between gap-2">
                <h2 class="font-semibold">Money</h2>
                <a href="{{ route('super.billing.index') }}" wire:navigate
                   class="text-xs font-semibold text-blue-600 hover:text-blue-700">Money due →</a>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($money as $row)
                    <div class="rounded-xl border p-3.5
                        {{ match ($row['tone']) {
                            'amber' => 'border-amber-100 bg-amber-50/60',
                            'rose' => 'border-rose-100 bg-rose-50/60',
                            'emerald' => 'border-emerald-100 bg-emerald-50/60',
                            default => 'border-slate-100 bg-slate-50/60',
                        } }}">
                        <p class="text-xs font-medium text-slate-500">{{ $row['label'] }}</p>
                        <p class="mt-0.5 truncate text-lg font-bold tabular-nums">{{ $big($row['money']) }}</p>
                        <p class="mt-0.5 truncate text-[0.7rem] text-slate-400">
                            {{ $row['count'] }}{{ $rest($row['money']) !== '' ? ' · and '.$rest($row['money']) : '' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         Who pays the platform most, and what shops can be given
    ------------------------------------------------------------------- --}}
    <div class="grid gap-4 xl:grid-cols-3">

        <div class="card rise rise-1 xl:col-span-2">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-semibold">The shops that pay most</h2>
                <p class="mt-0.5 text-xs text-slate-500">What each one has paid the platform, all time.</p>
            </div>

            @if ($topShops === [])
                <div class="py-14 text-center">
                    <p class="text-sm font-medium">No payment has been confirmed yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                        Shops pay by bKash or bank and staff confirm it on the money-due screen. The first one
                        confirmed appears here.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($topShops as $row)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="w-5 shrink-0 text-sm font-bold text-slate-300">{{ $loop->iteration }}</span>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-xs font-bold text-blue-600">
                                {{ $initials($row['shop']->name) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $row['shop']->name }}</span>
                                <span class="block truncate text-xs text-slate-400">{{ $row['shop']->slug }}</span>
                            </span>
                            <span class="text-end">
                                <span class="block text-sm font-bold tabular-nums">
                                    {{ $row['money']->currency }} {{ $row['money']->toDisplay() }}
                                </span>
                                @if ($row['change'] !== null)
                                    <span class="block text-[0.7rem] font-semibold {{ $row['change']['up'] ? 'text-emerald-600' : 'text-rose-600' }}">
                                        {{ $row['change']['up'] ? '↑' : '↓' }} {{ $row['change']['percent'] }}% on last month
                                    </span>
                                @else
                                    <span class="block text-[0.7rem] text-slate-400">nothing to compare yet</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- What a shop can be given --}}
        <div class="card rise rise-2 p-5">
            <h2 class="font-semibold">What shops can have</h2>

            <div class="mt-4 space-y-2.5">
                @foreach ($catalogue as $row)
                    <a href="{{ route($row['route']) }}" wire:navigate
                       class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50">
                        <span class="plat-icon">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $icons[$row['icon']] }}" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ $row['label'] }}</span>
                            <span class="block text-xs text-slate-400">
                                {{ $row['count'] }} of {{ $row['of'] }} {{ $row['word'] }}
                            </span>
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <path d="m9 6 6 6-6 6" />
                        </svg>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         The machine underneath, and what staff have been doing
    ------------------------------------------------------------------- --}}
    <div class="grid gap-4 xl:grid-cols-3">

        <div class="card rise rise-1 p-5">
            <h2 class="font-semibold">The machine underneath</h2>
            <p class="mt-0.5 text-xs text-slate-500">Only what can actually be measured from this server.</p>

            <ul class="mt-4 space-y-3.5">
                @foreach ($health as $row)
                    <li>
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="text-slate-600">{{ $row['label'] }}</span>
                            <span class="flex items-center gap-1.5 font-semibold tabular-nums">
                                <span class="h-2 w-2 rounded-full
                                    {{ match ($row['tone']) {
                                        'bad' => 'bg-rose-500',
                                        'warn' => 'bg-amber-400',
                                        default => 'bg-emerald-500',
                                    } }}"></span>
                                {{ $row['value'] }}
                            </span>
                        </div>

                        @if (isset($row['bar']))
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $row['tone'] === 'bad' ? 'bg-rose-500' : ($row['tone'] === 'warn' ? 'bg-amber-400' : 'bg-emerald-500') }}"
                                     style="width: {{ max(2, round($row['bar'] * 100)) }}%"></div>
                            </div>
                        @endif

                        @if (isset($row['note']))
                            <p class="mt-0.5 text-[0.7rem] text-slate-400">{{ $row['note'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card rise rise-2 p-5 xl:col-span-2">
            <h2 class="font-semibold">What staff have been doing</h2>
            <p class="mt-0.5 text-xs text-slate-500">Everything platform staff did that reached into a shop.</p>

            @if ($activity->isEmpty())
                <div class="py-12 text-center">
                    <p class="text-sm font-medium">Nothing written down yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                        Confirming a payment, changing a shop's plan or granting a gateway all get recorded here.
                    </p>
                </div>
            @else
                <ul class="mt-4 space-y-3.5">
                    @foreach ($activity as $entry)
                        <li class="flex gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-500"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm">
                                    <span class="font-medium">{{ $entry->admin_name ?? 'Staff' }}</span>
                                    <span class="text-slate-600">{{ $entry->note ?? $entry->action }}</span>
                                    @if ($entry->tenant_name)
                                        <span class="text-slate-400">· {{ $entry->tenant_name }}</span>
                                    @endif
                                </span>
                                <span class="block text-[0.7rem] text-slate-400">
                                    {{ $entry->created_at?->diffForHumans() }}
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
