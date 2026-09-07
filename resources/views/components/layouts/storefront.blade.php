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
])

@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
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
    {{ $head ?? '' }}
    @vite(['resources/css/app.css', 'resources/js/storefront.js'])
</head>
<body class="min-h-full {{ $bodyClass }} text-slate-900 antialiased">

    {{ $above ?? '' }}

    <x-storefront.shop-header :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent" />

    {{-- Everything that changes from page to page, and nothing that does not --}}
    <div data-page-body>
        {{ $slot }}
    </div>

    <footer class="mt-10 border-t border-slate-100 bg-white">
        <div class="mx-auto {{ $wide ? 'max-w-7xl' : 'max-w-6xl' }} px-4 py-8 text-sm text-slate-500">
            @isset($footer)
                {{ $footer }}
            @else
                <p class="font-semibold text-slate-900">{{ $store->name }}</p>
                @if ($store->email)
                    <a href="mailto:{{ $store->email }}" class="mt-1 block hover:text-slate-900">{{ $store->email }}</a>
                @endif
            @endisset
        </div>
    </footer>

    <x-skeleton.shapes :accent="$accent" />
</body>
</html>
