@props(['product', 'accent' => '#16a34a'])

@php
    $variant = $product->defaultVariant();
    $image = $product->primaryImage();
    $symbol = config('currencies.'.($variant?->currency ?? 'BDT').'.symbol', '');
    $discounted = $variant?->isDiscounted() ?? false;
    $off = $discounted
        ? (int) floor(100 - ($variant->price_minor / $variant->compare_at_price_minor * 100))
        : 0;
    $sellable = $variant !== null && \App\Services\Storefront\Basket::canSell($variant);
    $url = route('storefront.product', $product->slug);
@endphp

{{--
    One thing for sale.

    The photograph sits alone in a white square, the way a shelf label does,
    and everything to read sits underneath it. The photo and the name open the
    product; the + puts one straight in the basket. Something that comes in
    sizes sends the shopper to choose one.
--}}
<div class="group flex flex-col">

    {{-- The photograph, and the one button that acts on it --}}
    <div class="relative">
        <a href="{{ $url }}" aria-label="{{ $product->name }}"
           class="block aspect-square overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 transition group-hover:shadow-md">
            @if ($image)
                <img src="{{ $image->thumbnailUrl() }}" alt="{{ $image->alt_text ?: $product->name }}"
                     loading="lazy"
                     class="h-full w-full object-cover transition duration-300 group-hover:scale-105 {{ $sellable ? '' : 'opacity-50 grayscale' }}">
            @else
                <div class="flex h-full w-full items-center justify-center text-slate-300">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="3"/>
                        <path d="m3 15 5-4 4 3 3-2 6 5"/>
                    </svg>
                </div>
            @endif

            @if (! $sellable)
                <span class="absolute inset-x-0 bottom-0 bg-slate-900/70 py-1 text-center text-[10px] font-semibold uppercase tracking-wide text-white">
                    Sold out
                </span>
            @endif
        </a>

        @if ($variant !== null && $sellable)
            @if ($product->has_variants)
                <a href="{{ $url }}" title="Choose a size" aria-label="Choose a size for {{ $product->name }}"
                   class="absolute bottom-2 end-2 flex h-8 w-8 items-center justify-center rounded-xl bg-white text-lg font-semibold shadow-md ring-1 ring-slate-100 transition hover:scale-110"
                   style="color: {{ $accent }}">+</a>
            @else
                <form method="POST" action="{{ route('storefront.basket.add') }}" class="absolute bottom-2 end-2">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <button type="submit" title="Add to basket" aria-label="Add {{ $product->name }} to basket"
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-white text-lg font-semibold shadow-md ring-1 ring-slate-100 transition hover:scale-110"
                            style="color: {{ $accent }}">+</button>
                </form>
            @endif
        @endif
    </div>

    {{-- What it is, and what it costs --}}
    <div class="mt-2.5 flex flex-1 flex-col gap-1">
        <h3 class="line-clamp-2 text-[13px] font-medium leading-snug text-slate-800">
            <a href="{{ $url }}" class="hover:underline">{{ $product->name }}</a>
        </h3>

        @if ($variant)
            <div class="mt-auto flex flex-wrap items-baseline gap-x-1.5 gap-y-1 pt-0.5">
                <span class="text-[15px] font-bold text-slate-900">{{ $symbol }}{{ $variant->price->toDisplay() }}</span>

                @if ($discounted)
                    <span class="text-xs text-slate-400 line-through">{{ $symbol }}{{ $variant->compareAtPrice->toDisplay() }}</span>
                @endif
            </div>

            @if ($discounted)
                <span class="w-fit rounded-md bg-rose-500 px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white">
                    &minus;{{ $off }}%
                </span>
            @endif
        @endif
    </div>
</div>
