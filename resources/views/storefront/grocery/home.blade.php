@php
    $accent = $template['accent'];
    $whereabouts = $location->label() ?: 'where you are';
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="$store->name.' — fresh groceries delivered'"
                      :description="'Order groceries and daily needs from '.$store->name.', delivered to your door.'">

    <x-slot:head>
        <meta property="og:title" content="{{ $store->name }}">
    </x-slot:head>

    {{-- Where the customer is, and what reaches them --}}
    <x-storefront.discover-hero :store="$store" :location="$location"
                                :search-url="$searchUrl" :accent="$accent" :figures="$figures" />

    <main class="mx-auto max-w-6xl px-4 py-8 sm:py-10">

        {{-- Looking for one thing in particular --}}
        <div class="mb-8">
            <x-storefront.search-box :accent="$accent" :value="request('q', '')" :example="$example" />
        </div>

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

        {{-- What we can send them, and nothing else --}}
        <section>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold sm:text-xl">
                        @if ($searching !== '')
                            Results for &ldquo;{{ $searching }}&rdquo;
                        @elseif ($location->isSet())
                            Available for you
                        @else
                            Fresh in the shop
                        @endif
                    </h2>
                    @if ($searching === '')
                        <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500 sm:text-[13px]">
                            @if ($location->isSet())
                                <x-storefront.icon name="pin" class="h-3.5 w-3.5 shrink-0" style="color: {{ $accent }}" />
                                <span class="truncate">Delivered to {{ $whereabouts }}</span>
                            @else
                                Tell us where you are and we will show only what reaches you.
                            @endif
                        </p>
                    @endif
                </div>

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
                <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($products as $product)
                        <x-storefront.product-card :product="$product" :accent="$accent" />
                    @endforeach
                </div>

                @if ($shelves->isNotEmpty())
                    <div class="mt-6 text-center">
                        <a href="{{ route('storefront.browse') }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border px-4 py-2 text-sm font-semibold transition hover:bg-white"
                           style="color: {{ $accent }}; border-color: {{ $accent }}40">
                            See everything we sell
                            <x-storefront.icon name="arrow" class="h-4 w-4" />
                        </a>
                    </div>
                @endif
            @endif
        </section>

        {{-- Then the shop laid out the way it is arranged: one row per aisle --}}
        @foreach ($shelves as $shelf)
            <x-storefront.product-shelf
                :title="$shelf['category']->name"
                :products="$shelf['products']"
                :accent="$accent"
                :image="$shelf['category']->hasImage() ? $shelf['category']->thumbnailUrl() : null"
                :letter="mb_substr($shelf['category']->name, 0, 1)"
                :subtitle="$shelf['products']->count() === 1
                    ? '1 item'
                    : $shelf['products']->count().($shelf['products']->count() === App\Services\Storefront\HomeShelves::PER_SHELF ? '+ items' : ' items')"
                :href="route('storefront.browse').'?category='.$shelf['category']->slug"
                class="mt-10" />
        @endforeach
    </main>
</x-layouts.storefront>
