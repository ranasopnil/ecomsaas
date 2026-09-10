@props([
    'title',
    'products',
    'accent' => '#16a34a',
    'subtitle' => null,
    'href' => null,
    'seeAll' => 'See all',
    'image' => null,
    'letter' => null,
])

@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
@endphp

{{--
    One row of the shop front.

    A heading, and the things underneath it on a line the shopper can push
    sideways with a thumb. The row never wraps: a category is a glance, not a
    page. "See all" is where the rest of it lives.
--}}
<section {{ $attributes->merge(['class' => 'mt-8']) }}>
    <div class="mb-3 flex items-end justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            @if ($image)
                <span class="hidden h-11 w-11 shrink-0 overflow-hidden rounded-2xl ring-1 ring-slate-100 sm:block">
                    <img src="{{ $image }}" alt="" loading="lazy" class="h-full w-full object-cover">
                </span>
            @elseif ($letter)
                <span class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg font-bold sm:flex"
                      style="background: {{ $accent }}1a; color: {{ $accent }}">{{ $letter }}</span>
            @endif

            <div class="min-w-0">
                <h2 class="truncate text-lg font-bold text-slate-900 sm:text-xl">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="truncate text-xs text-slate-500 sm:text-[13px]">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        @if ($href)
            <a href="{{ $href }}" data-swap-link data-no-skeleton
               class="group inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-[13px] font-semibold transition hover:bg-slate-100"
               style="color: {{ $accent }}">
                {{ $seeAll }}
                <x-storefront.icon name="arrow"
                                   class="h-4 w-4 transition group-hover:translate-x-0.5 {{ $rtl ? 'rotate-180 group-hover:-translate-x-0.5' : '' }}" />
            </a>
        @endif
    </div>

    <div class="swipe-row -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2
                sm:mx-0 sm:snap-none sm:px-0">
        @foreach ($products as $product)
            <div class="w-36 shrink-0 snap-start sm:w-40 lg:w-44">
                <x-storefront.product-card :product="$product" :accent="$accent" />
            </div>
        @endforeach
    </div>
</section>
