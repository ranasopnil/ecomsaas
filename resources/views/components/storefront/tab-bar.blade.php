@props([
    'accent' => '#16a34a',
    'hasOffers' => false,
])

@php
    $basket = app(\App\Services\Storefront\Basket::class);
    $inBasket = $basket->count();

    $here = fn (string $name) => request()->routeIs($name);

    // Only places that exist. "Offers" is left out of a shop with nothing
    // reduced rather than leading somewhere empty.
    $tabs = array_values(array_filter([
        [
            'label' => 'Home',
            'href' => route('storefront.home'),
            'icon' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
            'on' => $here('storefront.home'),
        ],
        [
            'label' => 'Browse',
            'href' => route('storefront.browse'),
            'icon' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
            'on' => $here('storefront.browse') && ! request()->boolean('offers'),
        ],
        $hasOffers ? [
            'label' => 'Offers',
            'href' => route('storefront.browse').'?offers=1',
            'icon' => 'M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9zM7.5 7.5h.01',
            'on' => $here('storefront.browse') && request()->boolean('offers'),
        ] : null,
        [
            'label' => 'Basket',
            'href' => route('storefront.basket'),
            'icon' => 'M3 9h18l-1.5 10.5a1 1 0 0 1-1 .5h-13a1 1 0 0 1-1-.5L3 9ZM8 9a4 4 0 0 1 8 0',
            'on' => $here('storefront.basket'),
            'count' => true,
        ],
    ]));
@endphp

{{--
    The strip along the bottom of a phone.

    Four places at most, always in the same order, always there. It is what
    turns a shop read on a phone into something walked about in: wherever a
    shopper is, home, the aisles and the basket are one thumb away. On
    anything wider than a phone it is not drawn at all — a mouse has the top
    bar and the whole page.
--}}
<nav class="app-tabs safe-x fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur md:hidden"
     aria-label="Shop">
    <div class="mx-auto flex max-w-lg items-stretch"
         x-data x-init="$store.basket.start({{ $inBasket }})">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['href'] }}" data-no-skeleton data-tab="{{ Str::slug($tab['label']) }}"
               class="tap relative flex flex-1 flex-col items-center justify-center gap-1 py-2.5 text-[0.65rem] font-semibold transition"
               style="{{ $tab['on'] ? 'color: '.$accent : 'color: #64748b' }}"
               @if ($tab['on']) aria-current="page" @endif>

                <span class="relative">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="{{ $tab['on'] ? '2' : '1.7' }}" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <path d="{{ $tab['icon'] }}" />
                    </svg>

                    @if ($tab['count'] ?? false)
                        {{-- Server first, so the number is right before any script runs --}}
                        <span x-show="$store.basket.count > 0"
                              x-transition.scale
                              @if ($inBasket === 0) x-cloak @endif
                              class="absolute -end-2 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[0.6rem] font-bold text-white"
                              style="background: {{ $accent }}"
                              x-text="$store.basket.count">{{ $inBasket ?: '' }}</span>
                    @endif
                </span>

                {{ $tab['label'] }}

                @if ($tab['on'])
                    <span class="absolute inset-x-5 top-0 h-0.5 rounded-b-full" style="background: {{ $accent }}"></span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
