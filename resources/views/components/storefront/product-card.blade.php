@props(['product', 'accent' => '#16a34a'])

@php
    $variant = $product->defaultVariant();
    $image = $product->primaryImage();
    $symbol = config('currencies.'.($variant?->currency ?? 'BDT').'.symbol', '');
    $discounted = $variant?->isDiscounted() ?? false;
@endphp

<a href="{{ route('storefront.product', $product->slug) }}"
   class="group flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 transition hover:shadow-md">

    <div class="relative aspect-square overflow-hidden bg-slate-50">
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
            @php($off = (int) round(100 - ($variant->price_minor / $variant->compare_at_price_minor * 100)))
            <span class="absolute start-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold text-white"
                  style="background: {{ $accent }}">{{ $off }}% off</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <h3 class="line-clamp-2 text-sm font-medium text-slate-900">{{ $product->name }}</h3>

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

            <span class="flex h-8 w-8 items-center justify-center rounded-full text-lg font-semibold text-white transition group-hover:scale-110"
                  style="background: {{ $accent }}" aria-hidden="true">+</span>
        </div>
    </div>
</a>
