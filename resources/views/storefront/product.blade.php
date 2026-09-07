@php
    $variants = $product->variants;
    $first = $product->defaultVariant();
    $mainImage = $product->primaryImage();
    $accent = $template['accent'];
    $symbol = config('currencies.'.($first?->currency ?? $store->currency).'.symbol', '');
    $exponent = $first?->currency_exponent ?? $store->currency_exponent;
    $videoId = $product->youtubeId();

    $sellable = collect($variants)->filter(fn ($v) => \App\Services\Storefront\Basket::canSell($v));

    // Everything the page needs to follow a shopper's choices without asking
    // the shop again. Prices are the shop's own; the bill is worked out again
    // when the order is placed.
    $variantData = $variants->map(fn ($v) => [
        'id' => $v->id,
        'name' => $v->name,
        'price' => $v->price_minor,
        'was' => $v->isDiscounted() ? $v->compare_at_price_minor : null,
        'off' => $v->isDiscounted()
            ? (int) floor(100 - ($v->price_minor / $v->compare_at_price_minor * 100))
            : 0,
        'sellable' => \App\Services\Storefront\Basket::canSell($v),
        'tracked' => (bool) ($v->inventory?->track_inventory ?? true),
        'stock' => (int) ($v->inventory?->available ?? 0),
        'image' => $v->displayImage()?->url(),
        'values' => $v->optionValues->pluck('id')->all(),
    ])->values();

    $optionData = $product->options->map(fn ($o) => [
        'id' => $o->id,
        'name' => $o->name,
        'values' => $o->values->map(fn ($v) => ['id' => $v->id, 'label' => $v->value])->values(),
    ])->values();
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="($product->meta_title ?: $product->name).' — '.$store->name"
                      :description="$product->meta_description ?: $product->short_description"
                      :robots="$isPreview ? 'noindex' : null"
                      wide>

    <x-slot:head>
        @if ($product->tags)
            <meta name="keywords" content="{{ implode(', ', $product->tags) }}">
        @endif
        <meta property="og:title" content="{{ $product->name }}">
        @if ($product->short_description)
            <meta property="og:description" content="{{ $product->short_description }}">
        @endif
        @if ($mainImage)
            <meta property="og:image" content="{{ $mainImage->url() }}">
        @endif
    </x-slot:head>

    @if ($isPreview)
        <x-slot:above>
            <div class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900">
                You are looking at this as a customer would. It is not on sale yet, so nobody else can see it.
                <a href="{{ route('admin.products.edit', $product) }}" class="underline">Back to editing</a>
            </div>
        </x-slot:above>
    @endif

    <main class="mx-auto max-w-7xl px-4 py-6"
          x-data="productPage(@js([
              'variants' => $variantData,
              'options' => $optionData,
              'symbol' => $symbol,
              'exponent' => $exponent,
              'image' => $mainImage?->url(),
              'max' => \App\Services\Storefront\Basket::MAX_PER_LINE,
          ]))">

        {{-- Where you are --}}
        <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <a href="{{ route('storefront.home') }}" class="hover:text-slate-900">Home</a>
            <span aria-hidden="true">›</span>
            @if ($category)
                <a href="{{ route('storefront.browse') }}?category={{ $category->slug }}" class="hover:text-slate-900">
                    {{ $category->name }}
                </a>
                <span aria-hidden="true">›</span>
            @endif
            <span class="font-medium text-slate-900">{{ $product->name }}</span>
        </nav>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">

            {{-- The product itself --}}
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <div class="grid gap-6 md:grid-cols-2">

                    {{-- Pictures --}}
                    <div>
                        <div class="aspect-square overflow-hidden rounded-2xl bg-slate-50 ring-1 ring-slate-100">
                            @if ($videoId)
                                <template x-if="showingVideo">
                                    <iframe class="h-full w-full"
                                            src="https://www.youtube-nocookie.com/embed/{{ $videoId }}?autoplay=1"
                                            title="{{ $product->name }}"
                                            referrerpolicy="strict-origin-when-cross-origin"
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                            allowfullscreen></iframe>
                                </template>
                            @endif

                            <template x-if="! showingVideo">
                                @if ($mainImage)
                                    <img x-bind:src="shown" src="{{ $mainImage->url() }}"
                                         alt="{{ $mainImage->alt_text ?: $product->name }}"
                                         class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-sm text-slate-400">
                                        No photo yet
                                    </div>
                                @endif
                            </template>
                        </div>

                        @if ($product->images->count() > 1 || $videoId)
                            <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                                @if ($videoId)
                                    <button type="button" x-on:click="playVideo()"
                                            class="relative flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-900/90 text-white ring-2 transition"
                                            x-bind:style="showingVideo ? 'box-shadow: 0 0 0 2px {{ $accent }}' : ''"
                                            aria-label="Play the video">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                                    </button>
                                @endif

                                @foreach ($product->images as $image)
                                    <button type="button" x-on:click="show(@js($image->url()))"
                                            class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200 transition"
                                            x-bind:style="(! showingVideo && shown === {{ Js::from($image->url()) }}) ? 'box-shadow: 0 0 0 2px {{ $accent }}' : ''">
                                        <img src="{{ $image->thumbnailUrl() }}" alt="{{ $image->alt_text ?: $product->name }}"
                                             class="h-full w-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- What it is, and buying it --}}
                    <div>
                        @if ($product->brand)
                            <p class="text-sm text-slate-500">{{ $product->brand->name }}</p>
                        @endif

                        <div class="mt-1 flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-bold">{{ $product->name }}</h1>

                            <template x-if="sellable">
                                <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                    In stock
                                </span>
                            </template>
                            <template x-if="! sellable">
                                <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                                    Sold out
                                </span>
                            </template>
                        </div>

                        @if ($product->short_description)
                            <p class="mt-2 text-sm text-slate-600">{{ $product->short_description }}</p>
                        @endif

                        @if ($product->unit)
                            <p class="mt-3 text-sm text-slate-500">
                                Sold <span class="font-medium text-slate-900">{{ $product->unit }}</span>
                            </p>
                        @endif

                        {{-- Price --}}
                        <div class="mt-3 flex flex-wrap items-baseline gap-3">
                            <span class="text-3xl font-bold" x-text="money(variant?.price ?? 0)">
                                {{ $symbol }}{{ $first?->price->toDisplay() }}
                            </span>
                            <template x-if="variant?.was">
                                <span class="text-lg text-slate-400 line-through" x-text="money(variant.was)"></span>
                            </template>
                            <template x-if="variant?.off">
                                <span class="rounded-md bg-rose-500 px-1.5 py-0.5 text-xs font-semibold text-white"
                                      x-text="'−' + variant.off + '%'"></span>
                            </template>
                        </div>

                        @if ($product->shipping_charge_minor === 0)
                            <p class="mt-2 text-sm font-medium" style="color: {{ $accent }}">Free delivery</p>
                        @endif

                        {{-- Choices --}}
                        @if ($optionData->isNotEmpty())
                            <div class="mt-5 space-y-4 rounded-2xl border border-slate-100 p-4">
                                @foreach ($optionData as $option)
                                    <div>
                                        <p class="mb-2 text-sm font-medium text-slate-700">
                                            {{ $option['name'] }}
                                            <span class="text-slate-400"
                                                  x-text="'(' + (@js($option['values'])).find(v => v.id === chosen[{{ $option['id'] }}])?.label + ')'"></span>
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($option['values'] as $value)
                                                <button type="button"
                                                        x-on:click="choose({{ $option['id'] }}, {{ $value['id'] }})"
                                                        x-bind:class="chosen[{{ $option['id'] }}] === {{ $value['id'] }}
                                                            ? 'border-transparent font-semibold text-slate-900'
                                                            : (available({{ $option['id'] }}, {{ $value['id'] }})
                                                                ? 'border-slate-200 text-slate-700 hover:border-slate-400'
                                                                : 'border-slate-100 text-slate-300 line-through')"
                                                        x-bind:style="chosen[{{ $option['id'] }}] === {{ $value['id'] }}
                                                            ? 'box-shadow: 0 0 0 2px {{ $accent }}' : ''"
                                                        class="rounded-lg border px-4 py-2 text-sm transition">
                                                    {{ $value['label'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- How many, and what that comes to --}}
                        <div class="mt-5 rounded-2xl border border-slate-100 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <p class="text-sm text-slate-500">
                                    Total
                                    <span class="ms-1 text-xl font-bold text-slate-900" x-text="money(totalMinor)"></span>
                                </p>

                                <div class="flex items-center rounded-xl bg-slate-100">
                                    <button type="button" x-on:click="less()" aria-label="One fewer"
                                            class="h-10 w-10 rounded-xl text-lg text-slate-600 hover:bg-slate-200">−</button>
                                    <span class="w-10 text-center text-sm font-semibold tabular-nums" x-text="quantity"></span>
                                    <button type="button" x-on:click="more()" aria-label="One more"
                                            x-bind:disabled="quantity >= ceiling"
                                            class="h-10 w-10 rounded-xl text-lg text-slate-600 hover:bg-slate-200 disabled:text-slate-300">+</button>
                                </div>
                            </div>

                            <template x-if="variant?.tracked && sellable && inStock <= 5">
                                <p class="mt-2 text-xs text-amber-700" x-text="'Only ' + inStock + ' left'"></p>
                            </template>

                            @if ($isPreview)
                                <p class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-center text-sm text-slate-500">
                                    Not on sale yet, so it cannot be bought.
                                </p>
                            @elseif ($sellable->isEmpty())
                                <p class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-center text-sm text-slate-500">
                                    Sold out. Please come back soon.
                                </p>
                            @else
                                <form method="POST" action="{{ route('storefront.basket.add') }}" class="mt-4 grid grid-cols-2 gap-3"
                                      x-data x-on:submit.prevent="$store.basket.add($el)">
                                    @csrf
                                    <input type="hidden" name="variant_id" x-bind:value="variant?.id">
                                    <input type="hidden" name="quantity" x-bind:value="quantity">
                                    <input type="hidden" name="then" x-ref="then" value="">

                                    <button type="submit" x-on:click="$refs.then.value = ''"
                                            x-bind:disabled="! sellable"
                                            class="rounded-xl bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:text-slate-400">
                                        Add to basket
                                    </button>

                                    <button type="submit" x-on:click="$refs.then.value = 'checkout'"
                                            x-bind:disabled="! sellable"
                                            class="rounded-xl px-5 py-3 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400"
                                            x-bind:style="sellable ? 'background: {{ $accent }}' : ''">
                                        Buy now
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- More from this shop --}}
            @if ($alsoHere->isNotEmpty())
                <aside class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <h2 class="mb-4 text-base font-bold">More from this shop</h2>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
                        @foreach ($alsoHere as $other)
                            <x-storefront.product-card :product="$other" :accent="$accent" />
                        @endforeach
                    </div>
                </aside>
            @endif
        </div>

        {{-- Everything about it --}}
        @if ($product->description || $first?->weightLabel() || $first?->dimensionsLabel() || $first?->sku || $product->tags)
            <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100 sm:p-6">
                <h2 class="mb-4 text-lg font-bold">Product details</h2>

                @if ($product->description)
                    <div class="shop-description text-sm">{!! $product->description !!}</div>
                @endif

                @php($details = array_filter([
                    'Sold' => $product->unit,
                    'Weight' => $first?->weightLabel(),
                    'Size' => $first?->dimensionsLabel(),
                    'Code' => $first?->sku,
                ]))

                @if ($details !== [])
                    <dl class="mt-6 max-w-md text-sm">
                        @foreach ($details as $label => $value)
                            <div class="flex justify-between border-b border-slate-100 py-2">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="font-medium">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($product->tags)
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($product->tags as $tag)
                            <a href="{{ route('storefront.browse') }}?q={{ urlencode($tag) }}"
                               class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600 hover:bg-slate-200">{{ $tag }}</a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </main>
</x-layouts.storefront>
