@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
    $accent = $template['accent'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $store->name }} — fresh groceries delivered</title>
    <meta name="description" content="Order groceries and daily needs from {{ $store->name }}, delivered to your door.">
    <meta property="og:title" content="{{ $store->name }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased">

    {{-- Top bar --}}
    <header class="sticky top-0 z-40 border-b border-slate-100 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3">
            <a href="{{ route('storefront.home') }}" class="flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl text-lg font-bold text-white"
                      style="background: {{ $accent }}">{{ mb_substr($store->name, 0, 1) }}</span>
                <span class="hidden text-base font-bold sm:block">{{ $store->name }}</span>
            </a>

            <div class="ms-auto flex items-center gap-2">
                <x-storefront.location-bar :location="$location" :search-url="$searchUrl" :accent="$accent" />
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden" style="background: linear-gradient(135deg, {{ $accent }} 0%, #065f46 100%)">
        <div class="pointer-events-none absolute -end-24 -top-32 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -start-16 bottom-[-8rem] h-72 w-72 rounded-full bg-lime-200/10 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 py-12 sm:py-16">
            <p class="text-xs font-semibold uppercase tracking-[.16em] text-white/70">Groceries &amp; daily needs</p>
            <h1 class="mt-2 text-3xl font-bold text-white sm:text-4xl">{{ $store->name }}</h1>
            <p class="mt-2 max-w-xl text-sm text-white/80">
                @if ($location->isSet())
                    Showing what we can deliver to {{ $location->label() ?: 'you' }}.
                @else
                    Tell us where you are and we will show you what we can bring to your door.
                @endif
            </p>

            <form action="{{ route('storefront.home') }}" method="GET" class="mt-6 flex max-w-xl gap-2">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search for rice, eggs, milk…"
                       class="w-full rounded-xl border-0 px-4 py-3 text-sm text-slate-900 shadow-sm focus:outline-none">
                <button type="submit" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white">Search</button>
            </form>
        </div>
    </section>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:py-10">

        {{-- Categories --}}
        @if ($categories->isNotEmpty())
            <section class="mb-10">
                <h2 class="mb-4 text-lg font-bold">Shop by category</h2>
                <div class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-2">
                    @foreach ($categories as $category)
                        <a href="{{ route('storefront.home') }}?category={{ $category->slug }}"
                           class="group flex w-24 shrink-0 flex-col items-center gap-2 text-center">
                            <span class="flex h-20 w-20 items-center justify-center rounded-full text-2xl transition group-hover:scale-105"
                                  style="background: {{ $accent }}1a; color: {{ $accent }}">
                                {{ mb_substr($category->name, 0, 1) }}
                            </span>
                            <span class="line-clamp-2 text-xs font-medium text-slate-600">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Products --}}
        <section>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-lg font-bold">
                    {{ $location->isSet() ? 'Available near you' : 'What we sell' }}
                </h2>
                @if ($hidden > 0)
                    <p class="text-xs text-slate-500">
                        {{ $hidden }} more {{ $hidden === 1 ? 'item is' : 'items are' }} not delivered to
                        {{ $location->label() ?: 'where you are' }}.
                    </p>
                @endif
            </div>

            @if ($products->isEmpty())
                <div class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-100">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-2xl"
                         style="background: {{ $accent }}1a; color: {{ $accent }}">!</div>
                    <h3 class="mt-4 text-lg font-semibold">
                        {{ $location->isSet() ? 'Nothing reaches you yet' : 'Nothing on the shelves yet' }}
                    </h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                        {{ $location->isSet()
                            ? 'This shop does not deliver to where you are. Try another address, or ask us to show everything.'
                            : 'This shop has not put anything on sale yet. Please come back soon.' }}
                    </p>
                    @if ($location->isSet())
                        <form method="POST" action="{{ route('storefront.location.forget') }}" class="mt-5">
                            @csrf
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-medium text-white"
                                    style="background: {{ $accent }}">Show me everything</button>
                        </form>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($products as $product)
                        <x-storefront.product-card :product="$product" :accent="$accent" />
                    @endforeach
                </div>
            @endif
        </section>
    </main>

    <footer class="mt-10 border-t border-slate-100 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-slate-500">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-semibold text-slate-900">{{ $store->name }}</p>
                    <p class="mt-1">
                        @if ($store->delivers_everywhere)
                            Delivering everywhere.
                        @else
                            Delivering within {{ rtrim(rtrim(number_format((float) $store->delivery_radius_km, 1), '0'), '.') }} km.
                        @endif
                    </p>
                </div>
                @if ($store->email)
                    <a href="mailto:{{ $store->email }}" class="hover:text-slate-900">{{ $store->email }}</a>
                @endif
            </div>
        </div>
    </footer>
</body>
</html>
