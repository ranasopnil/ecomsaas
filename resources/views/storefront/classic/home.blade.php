@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
    $accent = $template['accent'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $store->name }}</title>
    <meta property="og:title" content="{{ $store->name }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white text-slate-900 antialiased">

    <header class="border-b border-slate-100">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-3 px-4 py-5">
            <a href="{{ route('storefront.home') }}" class="text-xl font-bold">{{ $store->name }}</a>
            <div class="ms-auto">
                <x-storefront.location-bar :location="$location" :search-url="$searchUrl" :accent="$accent" />
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10">
        @if ($categories->isNotEmpty())
            <nav class="mb-8 flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <a href="{{ route('storefront.home') }}?category={{ $category->slug }}"
                       class="rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200">
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ $location->isSet() ? 'Available near you' : 'What we sell' }}</h1>
            @if ($hidden > 0)
                <p class="text-xs text-slate-500">
                    {{ $hidden }} more {{ $hidden === 1 ? 'item is' : 'items are' }} not delivered to
                    {{ $location->label() ?: 'where you are' }}.
                </p>
            @endif
        </div>

        @if ($products->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 p-10 text-center">
                <h2 class="text-lg font-semibold">
                    {{ $location->isSet() ? 'Nothing reaches you yet' : 'Nothing on sale yet' }}
                </h2>
                <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                    {{ $location->isSet()
                        ? 'This shop does not deliver to where you are.'
                        : 'This shop has not put anything on sale yet. Please come back soon.' }}
                </p>
                @if ($location->isSet())
                    <form method="POST" action="{{ route('storefront.location.forget') }}" class="mt-5">
                        @csrf
                        <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white">
                            Show me everything
                        </button>
                    </form>
                @endif
            </div>
        @else
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" :accent="$accent" />
                @endforeach
            </div>
        @endif
    </main>

    <footer class="mt-10 border-t border-slate-100">
        <div class="mx-auto max-w-5xl px-4 py-8 text-sm text-slate-500">
            {{ $store->name }}
            @if ($store->email) — <a href="mailto:{{ $store->email }}" class="hover:text-slate-900">{{ $store->email }}</a> @endif
        </div>
    </footer>
</body>
</html>
