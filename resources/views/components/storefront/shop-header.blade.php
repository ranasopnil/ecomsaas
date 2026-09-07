@props(['store', 'location', 'searchUrl', 'accent' => '#16a34a'])

@php
    $basket = app(\App\Services\Storefront\Basket::class);
    $inBasket = $basket->count();
    $added = session('basket.added');
    $refused = session('basket.refused');
@endphp

{{--
    The bar along the top of every page of a shop: the shop's name, where the
    customer is, and their basket. The same on the front, while browsing, on a
    product and in the basket, so nothing moves about under them.
--}}
<header class="sticky top-0 z-40 border-b border-slate-100 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
        <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl text-lg font-bold text-white"
                  style="background: {{ $accent }}">{{ mb_substr($store->name, 0, 1) }}</span>
            <span class="hidden text-base font-bold sm:block">{{ $store->name }}</span>
        </a>

        <x-storefront.location-bar :location="$location" :search-url="$searchUrl" :accent="$accent" />

        <div class="ms-auto flex items-center gap-2"
             x-data x-init="$store.basket.start({{ $inBasket }})">
            <a href="{{ route('storefront.basket') }}" title="Your basket" aria-label="Your basket"
               class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-700 transition hover:bg-slate-100">
                <x-storefront.icon name="basket" class="h-6 w-6" />
                {{-- Server first, so the number is right before any script runs --}}
                <span x-show="$store.basket.count > 0"
                      x-transition.scale
                      @if ($inBasket === 0) x-cloak @endif
                      class="absolute -end-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-bold text-white"
                      style="background: {{ $accent }}"
                      x-text="$store.basket.count">{{ $inBasket ?: '' }}</span>
            </a>
        </div>
    </div>
</header>

{{-- The small note in the corner, for when nothing reloaded --}}
<div x-data x-cloak
     class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:bottom-6 sm:end-6 sm:items-end sm:px-0">
    <template x-for="note in $store.basket.notes" :key="note.id">
        <div x-transition.opacity.duration.200ms
             class="pointer-events-auto flex max-w-sm items-center gap-3 rounded-xl bg-white px-4 py-3 text-sm shadow-lg ring-1 ring-slate-100">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white"
                  x-bind:style="note.tone === 'ok' ? 'background: {{ $accent }}' : 'background: #e11d48'">
                <x-storefront.icon name="check" class="h-4 w-4" x-show="note.tone === 'ok'" />
                <span x-show="note.tone !== 'ok'" class="text-base font-bold">!</span>
            </span>
            <span class="min-w-0 flex-1 text-slate-800" x-text="note.text"></span>
            <a href="{{ route('storefront.basket') }}" x-show="note.tone === 'ok'"
               class="shrink-0 text-xs font-semibold underline-offset-4 hover:underline"
               style="color: {{ $accent }}">Basket</a>
            <button type="button" x-on:click="$store.basket.dismiss(note.id)"
                    class="shrink-0 text-slate-300 hover:text-slate-600" aria-label="Close">&times;</button>
        </div>
    </template>
</div>

{{-- A word back after something goes in, or cannot --}}
@if ($added)
    <div class="border-b border-emerald-100 bg-emerald-50">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 text-sm text-emerald-900">
            <span class="flex items-center gap-2">
                <x-storefront.icon name="check" class="h-4 w-4" />
                <strong>{{ $added }}</strong> is in your basket.
            </span>
            <a href="{{ route('storefront.basket') }}" class="font-medium underline underline-offset-4">View basket</a>
        </div>
    </div>
@elseif ($refused)
    <div class="border-b border-rose-100 bg-rose-50">
        <div class="mx-auto max-w-7xl px-4 py-2 text-sm text-rose-900">{{ $refused }}</div>
    </div>
@endif
