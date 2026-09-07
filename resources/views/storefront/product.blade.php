@php
    $variants = $product->variants;
    $first = $product->defaultVariant();
    $mainImage = $product->primaryImage();
    $tracked = $first?->inventory?->track_inventory ?? true;
    $available = (int) ($first?->inventory?->available ?? 0);
    $accent = $template['accent'];
    $sellable = collect($variants)->filter(fn ($v) => \App\Services\Storefront\Basket::canSell($v));
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="($product->meta_title ?: $product->name).' — '.$store->name"
                      :description="$product->meta_description ?: $product->short_description"
                      :robots="$isPreview ? 'noindex' : null"
                      body-class="bg-white">

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

    <main class="mx-auto max-w-5xl px-4 py-10">
        <div class="grid gap-10 md:grid-cols-2">
            <div x-data="{ shown: @js($mainImage?->url()) }">
                <div class="aspect-square overflow-hidden rounded-xl bg-slate-100">
                    @if ($mainImage)
                        <img :src="shown" src="{{ $mainImage->url() }}" alt="{{ $mainImage->alt_text ?: $product->name }}"
                             class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-sm text-slate-400">
                            No photo yet
                        </div>
                    @endif
                </div>

                @if ($product->images->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @foreach ($product->images as $image)
                            <button type="button" @click="shown = @js($image->url())"
                                    class="aspect-square overflow-hidden rounded-lg border border-slate-200">
                                <img src="{{ $image->thumbnailUrl() }}" alt="{{ $image->alt_text ?: $product->name }}"
                                     class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                @if ($product->brand)
                    <p class="text-sm text-slate-500">{{ $product->brand->name }}</p>
                @endif

                <h1 class="mt-1 text-3xl font-semibold">{{ $product->name }}</h1>

                @if ($product->short_description)
                    <p class="mt-2 text-slate-600">{{ $product->short_description }}</p>
                @endif

                @if ($first)
                    <div class="mt-4 flex items-baseline gap-3">
                        <span class="text-2xl font-semibold tabular-nums">
                            {{ $first->currency }} {{ $first->price->toDisplay() }}
                        </span>
                        @if ($first->isDiscounted())
                            <span class="text-lg text-slate-400 line-through tabular-nums">
                                {{ $first->currency }} {{ number_format($first->compare_at_price_minor / (10 ** $first->currency_exponent), $first->currency_exponent) }}
                            </span>
                            @php($off = round(($first->compare_at_price_minor - $first->price_minor) / $first->compare_at_price_minor * 100))
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-sm font-medium text-rose-800">{{ $off }}% off</span>
                        @endif
                    </div>
                @endif

                @if ($product->has_variants)
                    <div class="mt-6 space-y-4">
                        @foreach ($product->options as $option)
                            <div>
                                <p class="mb-2 text-sm font-medium">{{ $option->name }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($option->values as $value)
                                        <span class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">{{ $value->value }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="mt-6 text-sm">
                    @if (! $tracked)
                        <span class="text-emerald-700">Available to order</span>
                    @elseif ($available > 0)
                        <span class="text-emerald-700">In stock</span>
                    @else
                        <span class="text-rose-700">Sold out</span>
                    @endif
                </p>

                @if ($product->shipping_charge_minor !== null && $first)
                    <p class="mt-2 text-sm text-slate-600">
                        @if ($product->shipping_charge_minor === 0)
                            <span class="font-medium text-emerald-700">Free delivery</span>
                        @else
                            Delivery {{ $first->currency }}
                            {{ number_format($product->shipping_charge_minor / (10 ** $first->currency_exponent), $first->currency_exponent) }}
                        @endif
                    </p>
                @endif

                @if ($isPreview || $sellable->isEmpty())
                    <button type="button" disabled
                            class="mt-6 w-full cursor-not-allowed rounded-lg bg-slate-300 px-5 py-3 text-sm font-medium text-slate-600">
                        {{ $isPreview ? 'Add to basket' : 'Sold out' }}
                    </button>
                @else
                    <form method="POST" action="{{ route('storefront.basket.add') }}" class="mt-6 space-y-3"
                          x-data x-on:submit.prevent="$store.basket.add($el)">
                        @csrf
                        @if ($product->has_variants && $variants->count() > 1)
                            <label class="block text-sm font-medium">
                                Which one
                                <select name="variant_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                    @foreach ($variants as $variant)
                                        <option value="{{ $variant->id }}" @disabled(! $sellable->contains('id', $variant->id))>
                                            {{ $variant->name ?: 'Standard' }} — {{ $variant->currency }} {{ $variant->price->toDisplay() }}{{ $sellable->contains('id', $variant->id) ? '' : ' (sold out)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @else
                            <input type="hidden" name="variant_id" value="{{ $sellable->first()->id }}">
                        @endif

                        <div class="flex gap-3">
                            <input type="number" name="quantity" value="1" min="1" max="{{ \App\Services\Storefront\Basket::MAX_PER_LINE }}"
                                   aria-label="How many" class="w-20 rounded-lg border border-slate-300 px-3 py-3 text-center text-sm">
                            <button type="submit"
                                    class="flex-1 rounded-lg px-5 py-3 text-sm font-medium text-white transition active:scale-[.98]"
                                    style="background: {{ $accent }}"
                                    x-bind:disabled="$store.basket.busy">Add to basket</button>
                        </div>
                    </form>
                @endif

                @if ($product->description)
                    <div class="shop-description mt-8 text-sm">
                        {!! $product->description !!}
                    </div>
                @endif

                @php($details = array_filter([
                    'Weight' => $first?->weightLabel(),
                    'Size' => $first?->dimensionsLabel(),
                    'Code' => $first?->sku,
                ]))

                @if ($details !== [])
                    <dl class="mt-8 border-t border-slate-200 pt-4 text-sm">
                        @foreach ($details as $label => $value)
                            <div class="flex justify-between border-b border-slate-100 py-2">
                                <dt class="text-slate-500">{{ $label }}</dt>
                                <dd class="font-medium">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($product->tags)
                    <div class="mt-6 flex flex-wrap gap-2">
                        @foreach ($product->tags as $tag)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        @php($videoId = $product->youtubeId())
        @if ($videoId)
            <section class="mt-12">
                <h2 class="mb-4 text-lg font-semibold">Watch it</h2>
                {{-- Built from the video's id alone, never from the link that was pasted in. --}}
                <div class="aspect-video overflow-hidden rounded-xl bg-slate-100">
                    <iframe class="h-full w-full"
                            src="https://www.youtube-nocookie.com/embed/{{ $videoId }}"
                            title="{{ $product->name }}"
                            loading="lazy"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                </div>
            </section>
        @endif
    </main>
</x-layouts.storefront>
