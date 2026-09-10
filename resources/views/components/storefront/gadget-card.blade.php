@props(['product', 'accent' => '#f26e21'])

@php
    $variant = $product->defaultVariant();
    $image = $product->primaryImage();
    $symbol = config('currencies.'.($variant?->currency ?? 'BDT').'.symbol', '');
    $discounted = $variant?->isDiscounted() ?? false;
    $off = $discounted
        ? (int) floor(100 - ($variant->price_minor / $variant->compare_at_price_minor * 100))
        : 0;
    $saving = $discounted
        ? new \App\Support\Money(
            $variant->compare_at_price_minor - $variant->price_minor,
            $variant->currency,
            $variant->currency_exponent,
        )
        : null;
    $sellable = $variant !== null && \App\Services\Storefront\Basket::canSell($variant);
    $url = route('storefront.product', $product->slug);
@endphp

{{--
    One gadget for sale.

    A gadget is bought on its make, its exact model and its price, so those
    are what this shows and in that order. The photograph is given room and
    never cropped — a phone photographed on white must not be cut off at the
    edges the way a bag of rice can be. A reduction is said in figures, not in
    the word "sale": the old price crossed out, the percentage, and how much
    is actually saved.
--}}
<div class="group flex h-full flex-col overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/70 transition hover:ring-slate-300 hover:shadow-lg">

    {{-- The photograph, given room --}}
    <div class="relative">
        <a href="{{ $url }}" aria-label="{{ $product->name }}"
           class="block aspect-square overflow-hidden bg-white p-4">
            @if ($image)
                <img src="{{ $image->thumbnailUrl() }}" alt="{{ $image->alt_text ?: $product->name }}"
                     loading="lazy"
                     class="h-full w-full object-contain transition duration-300 group-hover:scale-105 {{ $sellable ? '' : 'opacity-40 grayscale' }}">
            @else
                <div class="flex h-full w-full items-center justify-center text-slate-200">
                    <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                        <rect x="6" y="2.5" width="12" height="19" rx="2.5" /><path d="M10.5 18.5h3" />
                    </svg>
                </div>
            @endif
        </a>

        @if ($discounted && $sellable)
            <span class="absolute start-3 top-3 rounded-lg px-2 py-1 text-[11px] font-bold leading-none text-white"
                  style="background: {{ $accent }}">
                &minus;{{ $off }}%
            </span>
        @endif

        @if (! $sellable)
            <span class="absolute start-3 top-3 rounded-lg bg-slate-900/80 px-2 py-1 text-[11px] font-bold leading-none text-white">
                Sold out
            </span>
        @endif
    </div>

    {{-- The make, the model, the price --}}
    <div class="flex flex-1 flex-col border-t border-slate-100 p-3.5">
        @if ($product->brand)
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $product->brand->name }}</span>
        @endif

        <h3 class="mt-0.5 line-clamp-2 text-[13px] font-semibold leading-snug text-slate-800">
            <a href="{{ $url }}" class="hover:underline">{{ $product->name }}</a>
        </h3>

        @if ($variant)
            <div class="mt-auto pt-2.5">
                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <span class="text-base font-bold text-slate-900">{{ $symbol }}{{ $variant->price->toDisplay() }}</span>

                    @if ($discounted)
                        <span class="text-xs text-slate-400 line-through">{{ $symbol }}{{ $variant->compareAtPrice->toDisplay() }}</span>
                    @endif
                </div>

                @if ($saving)
                    <p class="mt-0.5 text-[11px] font-semibold" style="color: {{ $accent }}">
                        Save {{ $symbol }}{{ $saving->toDisplay() }}
                    </p>
                @endif

                <div class="mt-2.5 flex items-center gap-2">
                    <span class="flex items-center gap-1 text-[11px] {{ $sellable ? 'text-emerald-600' : 'text-slate-400' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $sellable ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                        {{ $sellable ? 'In stock' : 'Out of stock' }}
                    </span>

                    @if ($variant !== null && $sellable)
                        @if ($product->has_variants)
                            <a href="{{ $url }}"
                               class="ms-auto rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-white transition hover:opacity-90"
                               style="background: {{ $accent }}">Choose</a>
                        @else
                            {{-- Posts on its own with no javascript; quietly with it --}}
                            <form method="POST" action="{{ route('storefront.basket.add') }}" class="ms-auto"
                                  x-data x-on:submit.prevent="$store.basket.add($el)">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                <button type="submit" aria-label="Add {{ $product->name }} to basket"
                                        class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold text-white transition hover:opacity-90 active:scale-95"
                                        style="background: {{ $accent }}">Add</button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
