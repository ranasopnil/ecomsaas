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
    @vite(['resources/css/app.css', 'resources/js/storefront.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-900 antialiased">

    <x-storefront.shop-header :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent" />

    {{-- Where the customer is, and what reaches them --}}
    <x-storefront.discover-hero :store="$store" :location="$location"
                                :search-url="$searchUrl" :accent="$accent" :figures="$figures" />

    <main class="mx-auto max-w-6xl px-4 py-8 sm:py-10">

        {{-- Looking for one thing in particular --}}
        <form action="{{ route('storefront.browse') }}" method="GET" class="mb-8">
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-slate-400">
                    <x-storefront.icon name="search" class="h-5 w-5" />
                </span>
                <input type="search" name="q" value="{{ request('q') }}"
                       placeholder="Search for rice, eggs, milk…" aria-label="Search the shop"
                       class="w-full rounded-xl border-0 bg-white py-3 pe-28 ps-12 text-sm text-slate-900 shadow-sm ring-1 ring-slate-100 focus:outline-none">
                <button type="submit"
                        class="absolute inset-y-1 end-1 rounded-lg px-5 text-sm font-medium text-white"
                        style="background: {{ $accent }}">Search</button>
            </div>
        </form>

        {{-- Categories --}}
        @if ($categories->isNotEmpty())
            <section class="mb-10">
                <h2 class="mb-4 text-lg font-bold">Shop by category</h2>
                <div class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-2">
                    @foreach ($categories as $category)
                        <a href="{{ route('storefront.browse') }}?category={{ $category->slug }}"
                           class="group flex w-24 shrink-0 flex-col items-center gap-2 text-center">
                            <span class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full text-2xl transition group-hover:scale-105"
                                  style="background: {{ $accent }}1a; color: {{ $accent }}">
                                @if ($category->hasImage())
                                    <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}"
                                         loading="lazy" class="h-full w-full object-cover">
                                @else
                                    {{ mb_substr($category->name, 0, 1) }}
                                @endif
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
                    @if ($searching !== '')
                        Results for &ldquo;{{ $searching }}&rdquo;
                    @else
                        {{ $location->isSet() ? 'Available near you' : 'What we sell' }}
                    @endif
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
                        @if ($searching !== '')
                            Nothing matched that
                        @else
                            {{ $location->isSet() ? 'Nothing reaches you yet' : 'Nothing on the shelves yet' }}
                        @endif
                    </h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                        @if ($searching !== '')
                            We could not find anything called &ldquo;{{ $searching }}&rdquo;. Try a shorter word.
                        @else
                            {{ $location->isSet()
                                ? 'This shop does not deliver to where you are. Try another address, or ask us to show everything.'
                                : 'This shop has not put anything on sale yet. Please come back soon.' }}
                        @endif
                    </p>
                    @if ($location->isSet() && $searching === '')
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
                        @if ($areas->isEmpty())
                            Delivering everywhere.
                        @else
                            Delivering to {{ $areas->pluck('name')->join(', ', ' and ') }}.
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
