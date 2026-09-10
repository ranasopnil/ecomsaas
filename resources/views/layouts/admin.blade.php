@php
    use App\Models\Order;
    use App\Models\Subscription;

    $store = App\Facades\Tenancy::current();
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);

    // Work waiting on the shopkeeper, shown against Orders and on the bell.
    $needsYou = Order::query()->whereIn('status', [
        Order::STATUS_PLACED, Order::STATUS_APPROVED, Order::STATUS_PROCESSING,
        Order::STATUS_HANDED_OVER, Order::STATUS_NOT_DELIVERED,
    ])->count();

    $brandNew = Order::query()->where('status', Order::STATUS_PLACED)->count();

    $plan = Subscription::query()->active()->with('package')->latest('id')->first();

    $sections = [
        '' => [
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
            ['route' => 'admin.orders.index', 'label' => 'Orders', 'icon' => 'receipt', 'count' => $needsYou],
            ['route' => 'admin.products.index', 'label' => 'Products', 'icon' => 'box'],
            ['route' => 'admin.categories.index', 'label' => 'Categories', 'icon' => 'grid'],
            ['route' => 'admin.brands.index', 'label' => 'Brands', 'icon' => 'tag'],
            ['route' => 'admin.stock.index', 'label' => 'Stock', 'icon' => 'layers'],
            ['route' => 'admin.couriers.index', 'label' => 'Couriers', 'icon' => 'van'],
            ['route' => 'admin.ledger.index', 'label' => 'Accounts', 'icon' => 'book'],
        ],
        'Sales channels' => [
            ['url' => url('/'), 'label' => 'Online store', 'icon' => 'shop', 'external' => true],
        ],
        'Settings' => [
            ['route' => 'admin.payments.index', 'label' => 'Payment methods', 'icon' => 'card'],
            ['route' => 'admin.delivery.edit', 'label' => 'Delivery area', 'icon' => 'pin'],
            ['route' => 'admin.domains.index', 'label' => 'Web address', 'icon' => 'globe'],
            ['route' => 'admin.mail.edit', 'label' => 'Email', 'icon' => 'mail'],
            ['route' => 'admin.templates.index', 'label' => 'Shop look', 'icon' => 'brush'],
            ['route' => 'admin.footer.edit', 'label' => 'Footer & pages', 'icon' => 'page'],
            ['route' => 'admin.plan.index', 'label' => 'Your plan', 'icon' => 'badge'],
            ['route' => 'admin.addons.index', 'label' => 'Add-ons', 'icon' => 'plus'],
        ],
    ];

    $icons = [
        'home' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'box' => 'M12 3 3 7.5v9L12 21l9-4.5v-9zM3 7.5 12 12l9-4.5M12 12v9',
        'grid' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
        'tag' => 'M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9zM7.5 7.5h.01',
        'layers' => 'M12 3 3 8l9 5 9-5zM3 13l9 5 9-5M3 17l9 5 9-5',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3.5 9h17M3.5 15h17M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
        'mail' => 'M3 6h18v12H3zM3 7l9 6 9-6',
        'card' => 'M3 6h18v12H3zM3 10h18M7 15h3',
        'receipt' => 'M5 3h14v18l-3-2-2 2-2-2-2 2-3-2zM8 8h8M8 12h8M8 16h5',
        'page' => 'M6 3h9l3.5 3.5V21H6zM15 3v4h3.5M9 12h6M9 16h4',
        'van' => 'M3 7h11v9H3zM14 10h4l3 3v3h-7zM7.5 16a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6zM17.5 16a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6z',
        'book' => 'M5 4.5A1.5 1.5 0 0 1 6.5 3H19v18H6.5A1.5 1.5 0 0 1 5 19.5zM8 8h8M8 12h5',
        'badge' => 'M12 3 4 6.2v5.3c0 4.6 3.3 8.2 8 9.5 4.7-1.3 8-4.9 8-9.5V6.2zM9.2 12l2 2 3.6-3.8',
        'plus' => 'M4 7.5A3.5 3.5 0 0 1 7.5 4h9A3.5 3.5 0 0 1 20 7.5v9a3.5 3.5 0 0 1-3.5 3.5h-9A3.5 3.5 0 0 1 4 16.5zM12 8.5v7M8.5 12h7',
        'shop' => 'M4 9.5 5.5 4h13L20 9.5M4 9.5h16M4 9.5v10h16v-10M9.5 19.5v-5h5v5',
        'pin' => 'M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11ZM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
        'brush' => 'M4 20c0-2 1.5-3 3-3s3 1 3 3-1.5 2-3 2-3 0-3-2ZM10 17 20 7l-3-3L7 14',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} — {{ $store?->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="shop-admin h-full text-slate-900 antialiased" x-data="{ menu: false }">
    <div id="route-progress" class="route-progress"></div>

    <div class="flex min-h-full">
        {{-- The rail down the side. It stays put while pages change. --}}
        {{-- Behind the drawer, on a phone --}}
        <div x-show="menu" x-cloak x-transition.opacity
             x-on:click="menu = false"
             class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" aria-hidden="true"></div>

        {{--
            The rail. A drawer a thumb pulls open on a phone, and the fixed
            column it has always been on anything wider. Without this a
            shopkeeper on a phone had no way to leave the page they landed on.
        --}}
        <aside class="rail flex-col justify-between overflow-y-auto p-4"
               x-bind:class="menu
                   ? 'fixed inset-y-0 start-0 z-50 flex w-72 shadow-2xl'
                   : 'hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0'"
               x-on:click="menu = false">
            <div>
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="mb-7 flex items-center gap-2.5 px-2 pt-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl text-white"
                          style="background-image: linear-gradient(135deg, #ff5a7a, #f5325b)">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 8h12l-1 12H7z" /><path d="M9 8a3 3 0 0 1 6 0" />
                        </svg>
                    </span>
                    <span class="truncate text-base font-bold tracking-tight">{{ $store?->name }}</span>
                </a>

                @foreach ($sections as $heading => $links)
                    @if ($heading !== '')
                        <p class="mb-2 mt-6 px-3 text-[0.68rem] font-semibold uppercase tracking-wider text-slate-400">
                            {{ $heading }}
                        </p>
                    @endif

                    <nav class="space-y-0.5">
                        @foreach ($links as $link)
                            @php($isHere = isset($link['route']) && request()->routeIs($link['route']))
                            <a href="{{ $link['url'] ?? route($link['route']) }}"
                               @if ($link['external'] ?? false) target="_blank" rel="noopener" @else wire:navigate @endif
                               class="rail-link {{ $isHere ? 'rail-link-active' : '' }}">
                                <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $icons[$link['icon']] }}" />
                                </svg>
                                {{ $link['label'] }}

                                @if (($link['count'] ?? 0) > 0)
                                    <span class="rail-count">{{ $link['count'] }}</span>
                                @elseif ($link['external'] ?? false)
                                    <svg class="ms-auto h-3.5 w-3.5 text-slate-300" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <path d="M7 17 17 7M9 7h8v8" />
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                @endforeach
            </div>

            {{-- What the shop is on, and the way to more --}}
            <div class="mt-8 rounded-2xl border border-rose-100 bg-rose-50/70 p-4">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-rose-500 shadow-sm">
                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 8 4.5 3.5L12 5l4.5 6.5L21 8l-1.8 10H4.8z" />
                    </svg>
                </span>
                <p class="mt-2.5 text-sm font-bold">{{ $plan?->package->name ?? 'No plan yet' }}</p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
                    @if ($plan === null)
                        Ask us to put your shop on a plan.
                    @else
                        See what it includes, or move to a bigger one.
                    @endif
                </p>
                <a href="{{ route('admin.plan.index') }}" wire:navigate
                   class="btn btn-primary mt-3 w-full justify-center !py-2 !text-[0.8rem]">
                    Your plan
                </a>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Top bar --}}
            <header class="sticky top-0 z-20 border-b border-slate-100 bg-white">
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <div class="flex min-w-0 flex-1 items-center gap-3">
                        <button type="button" x-on:click.stop="menu = true"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-600 hover:bg-slate-50 lg:hidden"
                                aria-label="Menu">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round">
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                        </button>

                        <a href="{{ route('admin.dashboard') }}" wire:navigate class="lg:hidden">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold text-white"
                                  style="background-image: linear-gradient(135deg, #ff5a7a, #f5325b)">
                                {{ mb_substr($store?->name ?? 'S', 0, 1) }}
                            </span>
                        </a>

                        <form action="{{ route('admin.products.index') }}" method="GET"
                              class="relative hidden w-full max-w-md sm:block">
                            <svg class="pointer-events-none absolute top-3 h-4 w-4 text-slate-400" style="inset-inline-start:1rem"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                            </svg>
                            <input type="search" name="search" placeholder="Search your products…"
                                   class="w-full rounded-xl border border-slate-200 bg-white py-2.5 ps-11 pe-3 text-sm placeholder:text-slate-400 focus:border-rose-300 focus:outline-none">
                        </form>
                    </div>

                    <div class="flex items-center gap-3">
                        {{-- Orders nobody has looked at yet --}}
                        <a href="{{ route('admin.orders.index') }}?show=new" wire:navigate
                           class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-50"
                           title="{{ $brandNew === 0 ? 'No new orders' : $brandNew.' new '.($brandNew === 1 ? 'order' : 'orders') }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7" />
                                <path d="M10.5 20a2 2 0 0 0 3 0" />
                            </svg>
                            @if ($brandNew > 0)
                                <span class="absolute -end-0.5 -top-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-rose-500 px-1 text-[0.65rem] font-bold text-white">
                                    {{ $brandNew }}
                                </span>
                            @endif
                        </a>

                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = ! open"
                                    class="flex items-center gap-2.5 rounded-xl px-1.5 py-1 hover:bg-slate-50">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600">
                                    {{ mb_substr(auth()->user()?->name ?? '?', 0, 1) }}
                                </span>
                                <span class="hidden text-start sm:block">
                                    <span class="block text-sm font-semibold leading-4">{{ auth()->user()?->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ auth()->user()?->isOwner() ? 'Shop owner' : 'Staff' }}</span>
                                </span>
                                <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </button>

                            <div x-show="open" @click.outside="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute end-0 z-30 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                <p class="px-3 py-2 text-xs text-slate-500">{{ auth()->user()?->email }}</p>
                                <a href="{{ url('/') }}" target="_blank" rel="noopener"
                                   class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Open my shop &nearr;</a>
                                <a href="{{ route('admin.plan.index') }}" wire:navigate
                                   class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Your plan</a>
                                <form method="POST" action="{{ route('admin.logout') }}">
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

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8" data-page-body>
                @if (session('status'))
                    <div class="rise mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
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
