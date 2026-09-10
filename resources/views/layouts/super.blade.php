@php
    use App\Models\Concerns\TenantScope;
    use Illuminate\Support\Str;
    use App\Models\SubscriptionPayment;
    use App\Models\Tenant;

    $me = auth('admin')->user();

    // Work waiting on staff: money a shop says it has sent that nobody has
    // checked yet, and shops still waiting to be let in.
    $waiting = SubscriptionPayment::query()->withoutGlobalScope(TenantScope::class)->waiting()->count();
    $pendingShops = Tenant::query()->where('status', Tenant::STATUS_PENDING)->count();
    $needsYou = $waiting + $pendingShops;

    // The bar across the top. Same places as the rail, for a quick jump.
    $pills = [
        'super.dashboard' => 'Overview',
        'super.packages.index' => 'Plans',
        'super.stores.index' => 'Shops',
        'super.billing.index' => 'Money due',
        'super.addons.index' => 'Add-ons',
        'super.gateways.index' => 'Gateways',
        'super.templates.index' => 'Templates',
        'super.maps.index' => 'Maps',
    ];

    $sections = [
        '' => [
            ['route' => 'super.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
            ['route' => 'super.stores.index', 'label' => 'Shops', 'icon' => 'shop', 'match' => 'super.stores.*'],
            ['route' => 'super.packages.index', 'label' => 'Plans & subscriptions', 'icon' => 'badge', 'match' => 'super.packages.*'],
            ['route' => 'super.billing.index', 'label' => 'Payments & money due', 'icon' => 'card', 'count' => $waiting],
        ],
        'What shops can have' => [
            ['route' => 'super.addons.index', 'label' => 'Add-ons', 'icon' => 'plus'],
            ['route' => 'super.templates.index', 'label' => 'Templates', 'icon' => 'brush'],
            ['route' => 'super.gateways.index', 'label' => 'Gateways', 'icon' => 'wallet'],
            ['route' => 'super.maps.index', 'label' => 'Maps', 'icon' => 'pin'],
        ],
    ];

    $icons = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'shop' => 'M4 9.5 5.5 4h13L20 9.5M4 9.5h16M4 9.5v10h16v-10M9.5 19.5v-5h5v5',
        'badge' => 'M12 3 4 6.2v5.3c0 4.6 3.3 8.2 8 9.5 4.7-1.3 8-4.9 8-9.5V6.2zM9.2 12l2 2 3.6-3.8',
        'card' => 'M3 6h18v12H3zM3 10h18M7 15h3',
        'plus' => 'M4 7.5A3.5 3.5 0 0 1 7.5 4h9A3.5 3.5 0 0 1 20 7.5v9a3.5 3.5 0 0 1-3.5 3.5h-9A3.5 3.5 0 0 1 4 16.5zM12 8.5v7M8.5 12h7',
        'brush' => 'M4 20c0-2 1.5-3 3-3s3 1 3 3-1.5 2-3 2-3 0-3-2ZM10 17 20 7l-3-3L7 14',
        'wallet' => 'M3 7.5A2.5 2.5 0 0 1 5.5 5H18v3M3 7.5V18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3M3 7.5h16a2 2 0 0 1 2 2V15M17 11.5h4v3.5h-4a1.75 1.75 0 0 1 0-3.5Z',
        'pin' => 'M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11ZM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Super admin' }} — Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="platform-admin h-full text-slate-900 antialiased" x-data="{ menu: false }">
    <div id="route-progress" class="route-progress"></div>

    {{-- The dark bar across the top --}}
    <header class="plat-bar sticky top-0 z-30">
        <div class="safe-x flex h-full items-center gap-3 px-4 sm:gap-4 sm:px-6">
            <button type="button" x-on:click.stop="menu = true"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-300 hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Menu">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>

            <a href="{{ route('super.dashboard') }}" wire:navigate class="flex shrink-0 items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl text-white"
                      style="background-image: linear-gradient(135deg, #60a5fa, #1d4ed8)">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8h12l-1 12H7z" /><path d="M9 8a3 3 0 0 1 6 0" />
                    </svg>
                </span>
                <span class="hidden sm:block">
                    <span class="block text-sm font-bold leading-4 text-white">Platform staff</span>
                    <span class="mt-0.5 inline-block rounded bg-blue-500/20 px-1.5 py-px text-[0.6rem] font-semibold uppercase tracking-wide text-blue-300">
                        Super admin
                    </span>
                </span>
            </a>

            <nav class="hidden min-w-0 flex-1 items-center gap-1 overflow-x-auto xl:flex">
                @foreach ($pills as $route => $label)
                    <a href="{{ route($route) }}" wire:navigate
                       class="plat-pill {{ request()->routeIs($route) ? 'plat-pill-active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <form action="{{ route('super.stores.index') }}" method="GET" class="relative ms-auto hidden w-full max-w-xs md:block xl:ms-0">
                <svg class="pointer-events-none absolute top-2.5 h-4 w-4 text-slate-400" style="inset-inline-start:.85rem"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                </svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search shops and owners…"
                       class="w-full rounded-xl border border-white/10 bg-white/5 py-2 ps-10 pe-3 text-sm text-white placeholder:text-slate-400 focus:border-blue-400 focus:bg-white/10 focus:outline-none">
            </form>

            <div class="ms-auto flex items-center gap-2 md:ms-0">
                {{-- What is waiting on somebody here --}}
                <a href="{{ route('super.billing.index') }}" wire:navigate
                   class="relative flex h-9 w-9 items-center justify-center rounded-xl text-slate-300 hover:bg-white/10 hover:text-white"
                   title="{{ $needsYou === 0 ? 'Nothing waiting' : $needsYou.' waiting on you' }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7" />
                        <path d="M10.5 20a2 2 0 0 0 3 0" />
                    </svg>
                    @if ($needsYou > 0)
                        <span class="absolute end-1 top-1.5 h-2 w-2 rounded-full bg-blue-400 ring-2 ring-[#101c3d]"></span>
                    @endif
                </a>

                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = ! open"
                            class="flex items-center gap-2.5 rounded-xl px-1.5 py-1 hover:bg-white/10">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-500/20 text-xs font-bold text-blue-200">
                            {{ Str::of($me?->name ?? '?')->explode(' ')->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->implode('') }}
                        </span>
                        <span class="hidden text-start lg:block">
                            <span class="block text-sm font-semibold leading-4 text-white">{{ $me?->name }}</span>
                            <span class="block text-[0.7rem] text-slate-400">Platform admin</span>
                        </span>
                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="m6 9 6 6 6-6" />
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute end-0 z-40 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1 shadow-xl">
                        <p class="px-3 py-2 text-xs text-slate-500">{{ $me?->email }}</p>
                        <a href="{{ route('super.stores.index') }}" wire:navigate
                           class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Shops</a>
                        <a href="{{ route('super.billing.index') }}" wire:navigate
                           class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Money due</a>
                        <form method="POST" action="{{ route('super.logout') }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg px-3 py-2 text-start text-sm hover:bg-slate-50">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-var(--plat-bar-height))]">
        {{-- The rail down the side --}}
        {{-- Behind the drawer, on a phone --}}
        <div x-show="menu" x-cloak x-transition.opacity
             x-on:click="menu = false"
             class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" aria-hidden="true"></div>

        {{--
            The rail. A drawer a thumb pulls open on a phone, and the fixed
            column it has always been on anything wider. Without this, staff
            on a phone had no way to leave the page they landed on.
        --}}
        <aside class="rail flex-col justify-between overflow-y-auto p-4"
               x-bind:class="menu
                   ? 'fixed inset-y-0 start-0 z-50 flex w-72 shadow-2xl'
                   : 'hidden lg:sticky lg:top-[var(--plat-bar-height)] lg:flex lg:h-[calc(100vh-var(--plat-bar-height))] lg:w-64 lg:shrink-0'"
               x-on:click="menu = false">
            <div>
                @foreach ($sections as $heading => $links)
                    @if ($heading !== '')
                        <p class="mb-2 mt-6 px-3 text-[0.68rem] font-semibold uppercase tracking-wider text-slate-400">
                            {{ $heading }}
                        </p>
                    @endif

                    <nav class="space-y-0.5">
                        @foreach ($links as $link)
                            @php($isHere = request()->routeIs($link['match'] ?? $link['route']))
                            <a href="{{ route($link['route']) }}" wire:navigate
                               class="rail-link {{ $isHere ? 'rail-link-active' : '' }}">
                                <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $icons[$link['icon']] }}" />
                                </svg>
                                {{ $link['label'] }}

                                @if (($link['count'] ?? 0) > 0)
                                    <span class="rail-count">{{ $link['count'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                @endforeach
            </div>

            {{-- What all this is for --}}
            <div class="mt-8 rounded-2xl border border-blue-100 bg-blue-50/70 p-4">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-blue-600 shadow-sm">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 8 4.5 3.5L12 5l4.5 6.5L21 8l-1.8 10H4.8z" />
                    </svg>
                </span>
                <p class="mt-2.5 text-sm font-bold">Grow the platform</p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
                    Every shop here is somebody's living.
                </p>
                <a href="{{ route('super.stores.index') }}" wire:navigate
                   class="mt-2.5 inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700">
                    See every shop
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                        <path d="M5 12h13M13 6l6 6-6 6" />
                    </svg>
                </a>
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8" data-page-body>
            @if (session('status'))
                <div class="rise mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rise mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Small messages that appear in the corner, then fade. --}}
    <div x-data="toasts" class="pointer-events-none fixed bottom-6 end-6 z-50 flex flex-col gap-2">
        <template x-for="item in items" :key="item.id">
            <div x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-3"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 class="pointer-events-auto rounded-xl border px-4 py-3 text-sm shadow-lg"
                 :class="item.tone === 'bad'
                    ? 'border-rose-200 bg-rose-50 text-rose-900'
                    : 'border-slate-200 bg-white text-slate-800'">
                <span x-text="item.text"></span>
            </div>
        </template>
    </div>

    @livewireScripts
    <x-skeleton.shapes />
</body>
</html>
