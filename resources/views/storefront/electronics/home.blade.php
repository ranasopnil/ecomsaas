@php
    $accent = $template['accent'];

    // A search or a category narrows the page, and the extra rows are not
    // asked for then. Defaults keep the page honest either way.
    $topSelling = $topSelling ?? collect();
    $offers = $offers ?? collect();
    $brands = $brands ?? collect();
    $promises = $promises ?? [];

    $browse = fn (array $with = []) => route('storefront.browse')
        .(($q = http_build_query(array_filter($with))) ? '?'.$q : '');

    // The one thing put at the front. The newest thing on sale that reaches
    // this customer — a real product with a real price, not a poster.
    $featured = $products->first();
    $featuredVariant = $featured?->defaultVariant();
    $featuredImage = $featured?->primaryImage();
    $symbol = config('currencies.'.($featuredVariant?->currency ?? $store->currency).'.symbol', '');
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="$store->name.' — phones, computers and gadgets'"
                      :description="'Buy phones, laptops and gadgets from '.$store->name.'. Official products, delivered to your door.'"
                      body-class="bg-slate-100"
                      wide>

    <x-slot:head>
        <meta property="og:title" content="{{ $store->name }}">
    </x-slot:head>

    <main class="mx-auto max-w-7xl px-4 py-6">

        {{-- ------------------------------------------------------------------
             The front of the shop: what is newest, and the way to search
        ------------------------------------------------------------------- --}}
        <section class="overflow-hidden rounded-3xl bg-slate-900 text-white">
            <div class="grid items-center gap-6 p-6 sm:p-9 lg:grid-cols-2">
                <div class="min-w-0">
                    @if ($searching !== '')
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color: {{ $accent }}">
                            You searched for
                        </p>
                        <h1 class="mt-2 text-2xl font-bold leading-tight sm:text-3xl">&ldquo;{{ $searching }}&rdquo;</h1>
                    @else
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color: {{ $accent }}">
                            {{ $store->name }}
                        </p>
                        <h1 class="mt-2 text-2xl font-bold leading-tight sm:text-3xl lg:text-4xl">
                            Phones, computers and everything that plugs in.
                        </h1>
                        <p class="mt-3 max-w-md text-sm text-slate-300">
                            @if ($location->isSet())
                                Everything below can be delivered to {{ $location->label() ?: 'where you are' }}.
                            @else
                                Tell us where you are and we will show only what reaches you.
                            @endif
                        </p>
                    @endif

                    <div class="mt-5 max-w-md">
                        <x-storefront.search-box :accent="$accent" :value="request('q', '')" :example="$example" />
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="{{ $browse() }}"
                           class="rounded-xl px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                           style="background: {{ $accent }}">See everything we sell</a>

                        @if ($offers->isNotEmpty())
                            <a href="{{ $browse(['offers' => 1]) }}"
                               class="rounded-xl border border-white/20 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                                What is reduced
                            </a>
                        @endif
                    </div>
                </div>

                {{-- The newest thing in the shop, shown as itself --}}
                @if ($featured && $featuredVariant)
                    <a href="{{ route('storefront.product', $featured->slug) }}"
                       class="group flex items-center gap-5 rounded-2xl bg-white/5 p-5 ring-1 ring-white/10 transition hover:bg-white/10">
                        <span class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white p-2.5 sm:h-36 sm:w-36">
                            @if ($featuredImage)
                                <img src="{{ $featuredImage->thumbnailUrl() }}"
                                     alt="{{ $featuredImage->alt_text ?: $featured->name }}" loading="lazy"
                                     class="h-full w-full object-contain transition duration-300 group-hover:scale-105">
                            @else
                                <span class="text-3xl font-bold text-slate-200">{{ mb_substr($featured->name, 0, 1) }}</span>
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[11px] font-bold uppercase tracking-wider" style="color: {{ $accent }}">
                                {{ $searching !== '' ? 'Best match' : 'Newest in the shop' }}
                            </span>
                            @if ($featured->brand)
                                <span class="mt-1.5 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                                    {{ $featured->brand->name }}
                                </span>
                            @endif
                            <span class="mt-0.5 block line-clamp-2 text-base font-bold sm:text-lg">{{ $featured->name }}</span>
                            <span class="mt-2 block text-xl font-bold" style="color: {{ $accent }}">
                                {{ $symbol }}{{ $featuredVariant->price->toDisplay() }}
                            </span>
                            @if ($featuredVariant->isDiscounted())
                                <span class="block text-xs text-slate-400 line-through">
                                    {{ $symbol }}{{ $featuredVariant->compareAtPrice->toDisplay() }}
                                </span>
                            @endif
                        </span>
                    </a>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------------------
             What this shop actually promises. Read from its own settings.
        ------------------------------------------------------------------- --}}
        @if ($promises !== [])
            <section class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($promises as $promise)
                    <div class="flex items-center gap-3 rounded-2xl bg-white p-3.5 ring-1 ring-slate-200/70">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                              style="background: {{ $accent }}1a; color: {{ $accent }}">
                            <x-storefront.icon :name="$promise['icon']" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-800">{{ $promise['title'] }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $promise['detail'] }}</span>
                        </span>
                    </div>
                @endforeach
            </section>
        @endif

        {{-- ------------------------------------------------------------------
             What the shop is arranged into
        ------------------------------------------------------------------- --}}
        @if ($categories->isNotEmpty() && $searching === '')
            <section class="mt-10">
                <div class="mb-4 flex items-end justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="h-6 w-1.5 shrink-0 rounded-full" style="background: {{ $accent }}"></span>
                        <h2 class="text-lg font-bold sm:text-xl">Shop by category</h2>
                    </div>
                    <a href="{{ $browse() }}" class="text-[13px] font-semibold" style="color: {{ $accent }}">Show all</a>
                </div>

                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    @foreach ($categories as $category)
                        <a href="{{ $browse(['category' => $category->slug]) }}"
                           class="group flex flex-col items-center gap-2 rounded-2xl bg-white p-3.5 text-center ring-1 ring-slate-200/70 transition hover:ring-slate-300 hover:shadow-md">
                            <span class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl text-xl font-bold transition group-hover:scale-105"
                                  style="background: {{ $accent }}1a; color: {{ $accent }}">
                                @if ($category->hasImage())
                                    <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}"
                                         loading="lazy" class="h-full w-full object-cover">
                                @else
                                    {{ mb_substr($category->name, 0, 1) }}
                                @endif
                            </span>
                            <span class="line-clamp-2 text-xs font-semibold text-slate-700">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------------------------
             The makes it stocks
        ------------------------------------------------------------------- --}}
        @if ($brands->isNotEmpty())
            <section class="mt-10">
                <div class="mb-4 flex items-center gap-3">
                    <span class="h-6 w-1.5 shrink-0 rounded-full" style="background: {{ $accent }}"></span>
                    <h2 class="text-lg font-bold sm:text-xl">Shop by brand</h2>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($brands as $row)
                        <a href="{{ $browse(['brand' => $row['brand']->slug]) }}"
                           class="flex flex-col items-center justify-center gap-1 rounded-2xl bg-white px-3 py-5 text-center ring-1 ring-slate-200/70 transition hover:ring-slate-300 hover:shadow-md">
                            <span class="line-clamp-1 text-sm font-bold text-slate-800">{{ $row['brand']->name }}</span>
                            <span class="text-[11px] text-slate-400">
                                {{ $row['items'] }} {{ $row['items'] === 1 ? 'item' : 'items' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ------------------------------------------------------------------
             What is reduced, what sells, what has just arrived
        ------------------------------------------------------------------- --}}
        @if ($offers->isNotEmpty())
            <x-storefront.gadget-rail title="Reduced right now" :products="$offers" :accent="$accent"
                                      subtitle="The price asked is below the price it was"
                                      :href="$browse(['offers' => 1])" />
        @endif

        @if ($topSelling->isNotEmpty())
            <x-storefront.gadget-rail title="Top selling" :products="$topSelling" :accent="$accent"
                                      subtitle="Counted from what this shop has actually sold"
                                      :href="$browse()" />
        @endif

        {{-- ------------------------------------------------------------------
             Everything that reaches this customer
        ------------------------------------------------------------------- --}}
        <section class="mt-10">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="h-6 w-1.5 shrink-0 rounded-full" style="background: {{ $accent }}"></span>
                    <div>
                        <h2 class="text-lg font-bold sm:text-xl">
                            @if ($searching !== '')
                                Results for &ldquo;{{ $searching }}&rdquo;
                            @else
                                New arrivals
                            @endif
                        </h2>
                        @if ($searching === '')
                            <p class="text-xs text-slate-500 sm:text-[13px]">The latest things put on the shelves</p>
                        @endif
                    </div>
                </div>

                @if ($hidden > 0)
                    <p class="text-xs text-slate-500">
                        {{ $hidden }} more {{ $hidden === 1 ? 'item is' : 'items are' }} not delivered to
                        {{ $location->label() ?: 'where you are' }}.
                    </p>
                @endif
            </div>

            @if ($products->isEmpty())
                <div class="rounded-2xl bg-white p-10 text-center ring-1 ring-slate-200/70">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl"
                         style="background: {{ $accent }}1a; color: {{ $accent }}">
                        <x-storefront.icon name="search" class="h-6 w-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold">
                        @if ($searching !== '')
                            Nothing matched that
                        @else
                            {{ $location->isSet() ? 'Nothing reaches you yet' : 'Nothing on the shelves yet' }}
                        @endif
                    </h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                        @if ($searching !== '')
                            We could not find anything called &ldquo;{{ $searching }}&rdquo;. Try a shorter word, or the
                            make on its own.
                        @else
                            {{ $location->isSet()
                                ? 'This shop does not deliver to where you are. Try another address, or ask us to show everything.'
                                : 'This shop has not put anything on sale yet. Please come back soon.' }}
                        @endif
                    </p>
                    @if ($location->isSet() && $searching === '')
                        <form method="POST" action="{{ route('storefront.location.forget') }}" class="mt-5">
                            @csrf
                            <button type="submit" class="rounded-xl px-4 py-2 text-sm font-bold text-white"
                                    style="background: {{ $accent }}">Show me everything</button>
                        </form>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
                    @foreach ($products as $product)
                        <x-storefront.gadget-card :product="$product" :accent="$accent" />
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ------------------------------------------------------------------
             Then the shop laid out the way it is arranged: one row per aisle
        ------------------------------------------------------------------- --}}
        @foreach ($shelves as $shelf)
            <x-storefront.gadget-rail
                :title="$shelf['category']->name"
                :products="$shelf['products']"
                :accent="$accent"
                :subtitle="$shelf['products']->count() === 1
                    ? '1 item'
                    : $shelf['products']->count().($shelf['products']->count() === App\Services\Storefront\HomeShelves::PER_SHELF ? '+ items' : ' items')"
                :href="$browse(['category' => $shelf['category']->slug])" />
        @endforeach
    </main>
</x-layouts.storefront>
