@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
    $accent = $template['accent'];
    $tints = ['#fce7f3', '#dbeafe', '#e5e7eb', '#ecfccb', '#fef3c7', '#fee2e2', '#fef9c3', '#ffedd5', '#ede9fe', '#ccfbf1'];
    $browse = fn (array $with = []) => route('storefront.browse').(($q = http_build_query(array_filter($with))) ? '?'.$q : '');
    $title = $wanted !== '' ? 'Results for “'.$wanted.'”'
        : ($current?->name ?? ($offersOnly ? 'On offer' : ($freeDeliveryOnly ? 'Free delivery' : 'Everything')));
@endphp
<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="$title.' — '.$store->name"
                      :description="'Browse '.$store->name.': '.$title.'.'"
                      wide>

    <div class="mx-auto max-w-7xl px-4 py-6 lg:flex lg:gap-6">

        {{-- Down the side: quick ways in, then every category --}}
        <aside class="mb-6 lg:mb-0 lg:w-64 lg:shrink-0" data-swap="side">
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 lg:sticky lg:top-20">
                <nav class="border-b border-slate-100 p-3">
                    <a href="{{ $browse(['offers' => 1]) }}" data-swap-link data-no-skeleton
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium {{ $offersOnly ? 'bg-slate-100 text-slate-900' : 'text-slate-700 hover:bg-slate-50' }}">
                        <x-storefront.icon name="tag" class="h-5 w-5 text-amber-500" /> Offers
                    </a>
                    @if ($hasFreeDelivery)
                        <a href="{{ $browse(['free-delivery' => 1]) }}" data-swap-link data-no-skeleton
                           class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium {{ $freeDeliveryOnly ? 'bg-slate-100 text-slate-900' : 'text-slate-700 hover:bg-slate-50' }}">
                            <x-storefront.icon name="bike" class="h-5 w-5 text-sky-500" /> Free delivery
                        </a>
                    @endif
                    <a href="{{ $browse() }}" data-swap-link data-no-skeleton
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium {{ ($current === null && ! $offersOnly && ! $freeDeliveryOnly && $wanted === '') ? 'bg-slate-100 text-slate-900' : 'text-slate-700 hover:bg-slate-50' }}">
                        <x-storefront.icon name="pin" class="h-5 w-5" style="color: {{ $accent }}" />
                        {{ $location->isSet() ? 'Near you' : 'Everything' }}
                    </a>
                </nav>

                @if ($categories->isNotEmpty())
                    <div class="p-3">
                        <h2 class="px-3 py-2 text-base font-bold text-slate-700">Categories</h2>
                        <ul>
                            @foreach ($categories as $category)
                                @php($openHere = $current !== null && ($current->id === $category->id || $current->parent_id === $category->id))
                                <li x-data="{ open: @js($openHere) }" class="border-t border-slate-100 first:border-0">
                                    <div class="flex items-center">
                                        <a href="{{ $browse(['category' => $category->slug]) }}" data-swap-link data-no-skeleton
                                           class="flex-1 px-3 py-2.5 text-sm {{ $current?->id === $category->id ? 'font-semibold text-slate-900' : 'text-slate-700 hover:text-slate-900' }}">
                                            {{ $category->name }}
                                        </a>
                                        @if ($category->children->isNotEmpty())
                                            <button type="button" x-on:click="open = ! open"
                                                    class="p-2 text-slate-400 hover:text-slate-700"
                                                    aria-label="Show what is in {{ $category->name }}">
                                                <x-storefront.icon name="chevron" class="h-4 w-4 transition"
                                                                   x-bind:class="open ? 'rotate-180' : ''" />
                                            </button>
                                        @endif
                                    </div>
                                    @if ($category->children->isNotEmpty())
                                        <ul x-show="open" x-cloak class="mb-2 ms-3 border-s border-slate-100">
                                            @foreach ($category->children as $child)
                                                <li>
                                                    <a href="{{ $browse(['category' => $child->slug]) }}" data-swap-link data-no-skeleton
                                                       class="block px-3 py-1.5 text-sm {{ $current?->id === $child->id ? 'font-semibold text-slate-900' : 'text-slate-600 hover:text-slate-900' }}">
                                                        {{ $child->name }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </aside>

        <main class="min-w-0 flex-1">

            {{-- Looking for something --}}
            <section class="rounded-2xl bg-white px-5 py-5 shadow-sm ring-1 ring-slate-100 sm:px-6">
                <div class="mx-auto flex max-w-3xl flex-wrap items-center gap-x-6 gap-y-3">
                    <div class="min-w-0">
                        <h1 class="text-lg font-bold tracking-tight text-slate-900">Search fresh essentials</h1>
                        <p class="text-xs text-slate-500">Find what you need and have it brought to your door.</p>
                    </div>
                    <div class="min-w-56 flex-1">
                        <x-storefront.search-box :accent="$accent" :value="$wanted"
                                                 :category="$current?->slug" :example="$example" />
                    </div>
                </div>
            </section>

            {{-- Everything below the search box changes with the category --}}
            <div data-swap="main">

            {{-- Categories as a row of circles --}}
            @if ($categories->isNotEmpty() && $wanted === '')
                <section class="mt-8">
                    <h2 class="mb-4 text-xl font-bold">Shop by categories</h2>
                    <div class="-mx-4 flex gap-5 overflow-x-auto px-4 pb-2">
                        @foreach ($categories as $category)
                            <a href="{{ $browse(['category' => $category->slug]) }}" data-swap-link data-no-skeleton
                               class="group flex w-28 shrink-0 flex-col items-center gap-2 text-center">
                                <span class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full text-2xl font-semibold transition group-hover:scale-105 {{ $current?->id === $category->id ? 'ring-2 ring-offset-2' : '' }}"
                                      style="background: {{ $tints[$loop->index % count($tints)] }}; color: {{ $accent }}; --tw-ring-color: {{ $accent }}">
                                    @if ($category->hasImage())
                                        <img src="{{ $category->thumbnailUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                    @else
                                        {{ mb_substr($category->name, 0, 1) }}
                                    @endif
                                </span>
                                <span class="line-clamp-1 text-sm font-medium text-slate-800">{{ $category->name }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- The strip about savings, only when there really are some --}}
            @if ($biggestSaving > 0 && ! $offersOnly)
                <a href="{{ $browse(['offers' => 1]) }}" data-swap-link data-no-skeleton
                   class="mt-6 flex items-center gap-4 rounded-2xl bg-amber-50 px-5 py-4 ring-1 ring-amber-100 transition hover:bg-amber-100/70">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-400 text-white">
                        <x-storefront.icon name="tag" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-bold text-slate-900">Save up to {{ $biggestSaving }}% today</span>
                        <span class="block text-sm text-slate-600">Real cuts on everyday things — see what is on offer</span>
                    </span>
                    <x-storefront.icon name="arrow" class="h-5 w-5 shrink-0 text-slate-700 {{ $rtl ? 'rotate-180' : '' }}" />
                </a>
            @endif

            {{-- Today's deals --}}
            @if ($deals->isNotEmpty())
                <section class="mt-8 rounded-2xl bg-amber-50/60 p-5 ring-1 ring-amber-100">
                    <div class="mb-4 flex items-end justify-between gap-3">
                        <div>
                            <h2 class="text-2xl font-bold text-amber-700">Today's deals</h2>
                            <p class="text-sm text-amber-800/80">Reduced for now — while they last.</p>
                        </div>
                        <a href="{{ $browse(['offers' => 1]) }}" data-swap-link data-no-skeleton class="text-sm font-medium text-amber-800 underline-offset-4 hover:underline">See all</a>
                    </div>
                    <div class="-mx-5 flex gap-4 overflow-x-auto px-5 pb-2">
                        @foreach ($deals as $product)
                            <div class="w-36 shrink-0 sm:w-40">
                                <x-storefront.product-card :product="$product" :accent="$accent" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Everything that matches --}}
            <section class="mt-8">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        @if ($current?->parent)
                            <p class="text-xs text-slate-500">
                                <a href="{{ $browse(['category' => $current->parent->slug]) }}" data-swap-link data-no-skeleton class="hover:underline">{{ $current->parent->name }}</a> ›
                            </p>
                        @endif
                        <h2 class="text-xl font-bold">{{ $title }}</h2>
                    </div>
                    <p class="text-sm text-slate-500">{{ $products->count() }} {{ $products->count() === 1 ? 'item' : 'items' }}</p>
                </div>

                @if ($products->isEmpty())
                    <div class="rounded-2xl bg-white p-10 text-center shadow-sm ring-1 ring-slate-100">
                        <h3 class="text-lg font-semibold">
                            {{ $wanted !== '' ? 'Nothing matched that' : 'Nothing here yet' }}
                        </h3>
                        <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                            @if ($wanted !== '')
                                We could not find anything called &ldquo;{{ $wanted }}&rdquo;. Try a shorter word.
                            @elseif ($location->isSet())
                                Nothing in here reaches {{ $location->label() ?: 'where you are' }} right now.
                            @else
                                Nothing has been put in here yet.
                            @endif
                        </p>
                        <a href="{{ $browse() }}" data-swap-link data-no-skeleton class="mt-5 inline-block rounded-xl px-4 py-2 text-sm font-medium text-white"
                           style="background: {{ $accent }}">See everything</a>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                        @foreach ($products as $product)
                            <x-storefront.product-card :product="$product" :accent="$accent" />
                        @endforeach
                    </div>
                @endif
            </section>
            </div>
        </main>
    </div>
</x-layouts.storefront>
