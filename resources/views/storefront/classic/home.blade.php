@php
    $accent = $template['accent'];
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="$store->name"
                      :description="'Shop at '.$store->name.'.'"
                      body-class="bg-white">

    <main class="mx-auto max-w-5xl px-4 py-10">
        @if ($categories->isNotEmpty())
            <nav class="mb-8 flex flex-wrap gap-2">
                @foreach ($categories as $category)
                    <a href="{{ route('storefront.browse') }}?category={{ $category->slug }}"
                       class="flex items-center gap-2 rounded-full bg-slate-100 py-1.5 pe-3 text-sm text-slate-700 hover:bg-slate-200 {{ $category->hasImage() ? 'ps-1.5' : 'ps-3' }}">
                        @if ($category->hasImage())
                            <img src="{{ $category->thumbnailUrl() }}" alt="" loading="lazy"
                                 class="h-6 w-6 rounded-full object-cover">
                        @endif
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
            <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($products as $product)
                    <x-storefront.product-card :product="$product" :accent="$accent" />
                @endforeach
            </div>
        @endif
    </main>

    <x-slot:footer>
        <p class="font-semibold text-slate-900">{{ $store->name }}</p>
        @if ($store->email)
            <a href="mailto:{{ $store->email }}" class="mt-1 block hover:text-slate-900">{{ $store->email }}</a>
        @endif
    </x-slot:footer>
</x-layouts.storefront>
