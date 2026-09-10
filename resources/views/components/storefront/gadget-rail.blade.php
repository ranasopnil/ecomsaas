@props([
    'title',
    'products',
    'accent' => '#f26e21',
    'subtitle' => null,
    'href' => null,
    'seeAll' => 'Show all',
])

@php
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
@endphp

{{--
    One row of an electronics shop front.

    A short bar of accent colour beside the heading, "Show all" on the far
    side, and the goods on a line the shopper can push sideways with a thumb.
    Wider cards than a grocery row, because a model name is longer than
    "Bananas" and cutting it in half helps nobody.
--}}
<section {{ $attributes->merge(['class' => 'mt-10']) }}>
    <div class="mb-4 flex items-end justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="h-6 w-1.5 shrink-0 rounded-full" style="background: {{ $accent }}"></span>
            <div class="min-w-0">
                <h2 class="truncate text-lg font-bold text-slate-900 sm:text-xl">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="truncate text-xs text-slate-500 sm:text-[13px]">{{ $subtitle }}</p>
                @endif
            </div>
        </div>

        @if ($href)
            <a href="{{ $href }}" data-swap-link data-no-skeleton
               class="group inline-flex shrink-0 items-center gap-1 rounded-lg border px-3 py-1.5 text-[13px] font-semibold transition"
               style="color: {{ $accent }}; border-color: {{ $accent }}40">
                {{ $seeAll }}
                <x-storefront.icon name="arrow"
                                   class="h-4 w-4 transition group-hover:translate-x-0.5 {{ $rtl ? 'rotate-180 group-hover:-translate-x-0.5' : '' }}" />
            </a>
        @endif
    </div>

    <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:snap-none sm:px-0">
        @foreach ($products as $product)
            <div class="w-44 shrink-0 snap-start sm:w-48 lg:w-52">
                <x-storefront.gadget-card :product="$product" :accent="$accent" />
            </div>
        @endforeach
    </div>
</section>
