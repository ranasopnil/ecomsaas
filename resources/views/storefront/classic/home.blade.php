@php
    $accent = $template['accent'];
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="$store->name"
                      :description="'Shop at '.$store->name.'.'"
                      body-class="bg-white">

    <main class="mx-auto max-w-5xl px-4 py-10">
        @if ($categories->isNotEmpty())
            <nav class="swipe-row -mx-4 mb-8 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                @foreach ($categories as $category)
                    <a href="{{ route('storefront.browse') }}?category={{ $category->slug }}"
                       class="tap flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-slate-100 py-1.5 pe-3 text-sm text-slate-700 hover:bg-slate-200 {{ $category->hasImage() ? 'ps-1.5' : 'ps-3' }}">
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
            <div>
                <h1 class="text-2xl font-bold">
                    @if ($searching !== '')
                        Results for &ldquo;{{ $searching }}&rdquo;
                    @elseif ($location->isSet())
                        Available for you
                    @else
                        What we sell
                    @endif
                </h1>
                @if ($searching === '' && $location->isSet())
                    <p class="mt-0.5 text-sm text-slate-500">Delivered to {{ $location->label() ?: 'where you are' }}.</p>
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
            <div class="rounded-2xl border border-dashed border-slate-200 p-10 text-center">
                <h2 class="text-lg font-semibold">
                    @if ($searching !== '')
                        Nothing matched that
                    @else
                        {{ $location->isSet() ? 'Nothing reaches you yet' : 'Nothing on sale yet' }}
                    @endif
                </h2>
                <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">
                    @if ($searching !== '')
                        We could not find anything called &ldquo;{{ $searching }}&rdquo;. Try a shorter word.
                    @else
                        {{ $location->isSet()
                            ? 'This shop does not deliver to where you are.'
                            : 'This shop has not put anything on sale yet. Please come back soon.' }}
                    @endif
                </p>
                @if ($location->isSet() && $searching === '')
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

        {{-- One row per category, so the whole shop can be seen from the front --}}
        @foreach ($shelves as $shelf)
            <x-storefront.product-shelf
                :title="$shelf['category']->name"
                :products="$shelf['products']"
                :accent="$accent"
                :image="$shelf['category']->hasImage() ? $shelf['category']->thumbnailUrl() : null"
                :letter="mb_substr($shelf['category']->name, 0, 1)"
                :href="route('storefront.browse').'?category='.$shelf['category']->slug"
                class="mt-10" />
        @endforeach
    </main>
</x-layouts.storefront>
