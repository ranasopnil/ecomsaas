@props([
    'store',
    'location',
    'searchUrl',
    'accent' => '#16a34a',
    'title' => null,
    'description' => null,
    'robots' => null,
    'bodyClass' => 'bg-slate-50',
    'wide' => false,
    'bottomBar' => false,
])

@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);

    // Whether the shop has anything reduced, so the tab strip only offers
    // "Offers" when there is something behind it. Asked once every few
    // minutes rather than on every page.
    $hasOffers = \Illuminate\Support\Facades\Cache::remember(
        'shop-has-offers:'.$store->id,
        now()->addMinutes(5),
        fn () => \App\Models\Product::query()->onSale()->whereHas(
            'variants',
            fn ($q) => $q->whereNotNull('compare_at_price_minor')
                ->whereColumn('compare_at_price_minor', '>', 'price_minor'),
        )->exists(),
    );
@endphp

{{--
    Every page a customer sees.

    Anything added here — the top bar, the notes in the corner, the grey
    outline shown while the next page is on its way — arrives on every shop
    page that uses this layout, including ones not written yet.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $store->name }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($robots)
        <meta name="robots" content="{{ $robots }}">
    @endif

    {{--
        What turns this into something a shopper can keep on their home
        screen: the shop's own icon, its own colour behind the status bar,
        and a manifest saying it opens without the browser's furniture.
    --}}
    <meta name="theme-color" content="{{ $accent }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $store->name }}">
    <meta name="format-detection" content="telephone=no">
    <link rel="manifest" href="{{ route('storefront.manifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ route('storefront.icon') }}">
    <link rel="apple-touch-icon" href="{{ route('storefront.icon.png') }}">

    {{ $head ?? '' }}
    @vite(['resources/css/app.css', 'resources/js/storefront.js'])
</head>
<body class="has-tabs {{ $bottomBar ? 'has-bar' : '' }} min-h-full {{ $bodyClass }} text-slate-900 antialiased">

    {{ $above ?? '' }}

    <x-storefront.shop-header :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent" />

    {{-- Everything that changes from page to page, and nothing that does not --}}
    <div data-page-body>
        {{ $slot }}
    </div>

    {{--
        The bottom of the page. A page can put its own words here; otherwise
        it gets the shop's own footer — address, links, and the pages the
        shopkeeper wrote — which is what nearly every page wants.
    --}}
    @isset($footer)
        <footer class="mt-10 border-t border-slate-100 bg-white">
            <div class="mx-auto {{ $wide ? 'max-w-7xl' : 'max-w-6xl' }} px-4 py-8 text-sm text-slate-500">
                {{ $footer }}
            </div>
        </footer>
    @else
        <x-storefront.site-footer :store="$store" :accent="$accent" />
    @endisset

    {{-- On a phone: home, the aisles and the basket, always one thumb away --}}
    <x-storefront.tab-bar :accent="$accent" :has-offers="$hasOffers" />

    <x-skeleton.shapes :accent="$accent" />
</body>
</html>
