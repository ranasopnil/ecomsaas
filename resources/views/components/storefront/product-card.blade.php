@props(['product', 'accent' => '#16a34a'])

@php
    $variant = $product->defaultVariant();
    $image = $product->primaryImage();
    $symbol = config('currencies.'.($variant?->currency ?? 'BDT').'.symbol', '');
    $discounted = $variant?->isDiscounted() ?? false;
    $sellable = $variant !== null && \App\Services\Storefront\Basket::canSell($variant);
    $url = route('storefront.product', $product->slug);
@endphp

{{--
    One thing for sale. The photo and the name open it; the + puts one straight
    in the basket. A product that comes in sizes sends the shopper to choose.
--}}
<div class="group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 transition hover:shadow-md">

    <a href="{{ $url }}" class="relative block aspect-square overflow-hidden bg-slate-50">
        @if ($image)
            <img src="{{ $image->thumbnailUrl() }}" alt="{{ $image->alt_text ?: $product->name }}"
                 loading="lazy"
                 class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-slate-300">
                <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="3"/>
                    <path d="m3 15 5-4 4 3 3-2 6 5"/>
                </svg>
            </div>
        @endif

        @if ($discounted)
            @php($off = (int) floor(100 - ($variant->price_minor / $variant->compare_at_price_minor * 100)))
            <span class="absolute start-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold text-white"
                  style="background: {{ $accent }}">{{ $off }}% off</span>
        @endif

        @if (! $sellable)
            <span class="absolute end-2 top-2 rounded-full bg-slate-900/70 px-2 py-0.5 text-xs font-medium text-white">Sold out</span>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <h3 class="line-clamp-2 text-sm font-medium text-slate-900">
            <a href="{{ $url }}" class="hover:underline">{{ $product->name }}</a>
        </h3>

        @if ($product->short_description)
            <p class="line-clamp-1 text-xs text-slate-400">{{ $product->short_description }}</p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-2 pt-2">
            <div>
                @if ($variant)
                    <div class="text-base font-bold" style="color: {{ $accent }}">
                        {{ $symbol }}{{ $variant->price->toDisplay() }}
                    </div>
                    @if ($discounted)
                        <div class="text-xs text-slate-400 line-through">
                            {{ $symbol }}{{ $variant->compareAtPrice->toDisplay() }}
                        </div>
                    @endif
                @endif
            </div>

            @if ($variant === null || ! $sellable)
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 text-lg text-slate-300" aria-hidden="true">+</span>
            @elseif ($product->has_variants)
                <a href="{{ $url }}" title="Choose a size" aria-label="Choose a size for {{ $product->name }}"
                   class="flex h-8 w-8 items-center justify-center rounded-full text-lg font-semibold text-white transition hover:scale-110"
                   style="background: {{ $accent }}">+</a>
            @else
                <form method="POST" action="{{ route('storefront.basket.add') }}">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <button type="submit" title="Add to basket" aria-label="Add {{ $product->name }} to basket"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-lg font-semibold text-white transition hover:scale-110"
                            style="background: {{ $accent }}">+</button>
                </form>
            @endif
        </div>
    </div>
</div>
