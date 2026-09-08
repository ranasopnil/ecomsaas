@php
    $store = App\Facades\Tenancy::current();
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);

    $sections = [
        'Shop' => [
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
            ['route' => 'admin.orders.index', 'label' => 'Orders', 'icon' => 'receipt'],
            ['route' => 'admin.products.index', 'label' => 'Products', 'icon' => 'box'],
            ['route' => 'admin.categories.index', 'label' => 'Categories', 'icon' => 'grid'],
            ['route' => 'admin.brands.index', 'label' => 'Brands', 'icon' => 'tag'],
            ['route' => 'admin.stock.index', 'label' => 'Stock', 'icon' => 'layers'],
            ['route' => 'admin.couriers.index', 'label' => 'Couriers', 'icon' => 'van'],
        ],
        'Settings' => [
            ['route' => 'admin.domains.index', 'label' => 'Web address', 'icon' => 'globe'],
            ['route' => 'admin.mail.edit', 'label' => 'Email', 'icon' => 'mail'],
            ['route' => 'admin.payments.index', 'label' => 'Payments', 'icon' => 'card'],
            ['route' => 'admin.delivery.edit', 'label' => 'Delivery area', 'icon' => 'globe'],
            ['route' => 'admin.templates.index', 'label' => 'Shop look', 'icon' => 'grid'],
            ['route' => 'admin.footer.edit', 'label' => 'Footer & pages', 'icon' => 'page'],
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
<body class="h-full text-slate-900 antialiased">
    <div id="route-progress" class="route-progress"></div>

    <div class="flex min-h-full">
        {{-- The rail down the side. It stays put while pages change. --}}
        <aside class="rail sticky top-0 hidden h-screen w-64 shrink-0 flex-col justify-between p-4 lg:flex">
            <div>
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="mb-8 flex items-center gap-2 px-2 pt-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-700 text-sm font-bold text-white">
                        {{ mb_substr($store?->name ?? 'S', 0, 1) }}
                    </span>
                    <span class="truncate text-base font-semibold text-white">{{ $store?->name }}</span>
                </a>

                @foreach ($sections as $heading => $links)
                    <p class="mb-2 px-3 text-[0.7rem] font-semibold uppercase tracking-wider text-slate-500">{{ $heading }}</p>
                    <nav class="mb-6 space-y-1">
                        @foreach ($links as $link)
                            <a href="{{ route($link['route']) }}" wire:navigate
                               class="rail-link {{ request()->routeIs($link['route']) ? 'rail-link-active' : '' }}">
                                <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $icons[$link['icon']] }}" />
                                </svg>
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </nav>
                @endforeach
            </div>

            <div class="rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-800 p-4 text-white">
                <p class="text-sm font-semibold">Your shop</p>
                <p class="mt-1 text-xs text-violet-100">
                    {{ $store?->slug }}{{ config('tenancy.subdomain_suffix') }}
                </p>
                <a href="{{ url('/') }}" target="_blank" rel="noopener"
                   class="mt-3 inline-flex items-center gap-1 text-xs font-medium underline underline-offset-2">
                    Open the shop &nearr;
                </a>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Top bar --}}
            <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/85 backdrop-blur">
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <a href="{{ route('admin.dashboard') }}" wire:navigate class="lg:hidden">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-700 text-sm font-bold text-white">
                                {{ mb_substr($store?->name ?? 'S', 0, 1) }}
                            </span>
                        </a>

                        <form action="{{ route('admin.products.index') }}" method="GET" class="relative hidden max-w-md flex-1 sm:block">
                            <svg class="pointer-events-none absolute top-2.5 h-4 w-4 text-slate-400" style="inset-inline-start:.75rem"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                            </svg>
                            <input type="search" name="search" placeholder="Search your products"
                                   class="w-72 rounded-xl border border-slate-200 bg-slate-50 py-2 ps-9 pe-3 text-sm placeholder:text-slate-400 focus:border-violet-400 focus:bg-white focus:outline-none">
                        </form>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="btn btn-quiet hidden sm:inline-flex">
                            View shop
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <path d="M7 17 17 7M9 7h8v8" />
                            </svg>
                        </a>

                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = ! open"
                                    class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2 py-1.5 text-sm hover:bg-slate-50">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-900 text-xs font-semibold text-white">
                                    {{ mb_substr(auth()->user()?->name ?? '?', 0, 1) }}
                                </span>
                                <span class="hidden text-start sm:block">
                                    <span class="block text-sm font-medium leading-4">{{ auth()->user()?->name }}</span>
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
                                 class="absolute end-0 z-30 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                                <p class="px-3 py-2 text-xs text-slate-500">{{ auth()->user()?->email }}</p>
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
