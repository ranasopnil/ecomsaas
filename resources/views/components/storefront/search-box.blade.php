@props([
    'accent' => '#16a34a',
    'value' => '',
    'category' => null,
    'example' => null,
])

{{--
    Searching the shop.

    An ordinary GET form, so pressing the button or the enter key searches
    whether or not javascript is running. With it, a few letters bring up what
    matches, with pictures and prices, and picking one goes straight to it.
--}}
<form action="{{ route('storefront.browse') }}" method="GET" data-no-skeleton data-swap-form
      x-data="productSearch(@js([
          'url' => route('storefront.search.suggest'),
          'query' => $value,
      ]))"
      x-on:click.outside="close()"
      x-on:keydown.escape="close()"
      class="relative">

    @if ($category)
        <input type="hidden" name="category" value="{{ $category }}">
    @endif

    <div class="flex items-center gap-2 rounded-full bg-slate-100 ps-4 transition focus-within:bg-white focus-within:ring-2"
         style="--tw-ring-color: {{ $accent }}">
        <span class="pointer-events-none shrink-0 text-slate-400">
            <x-storefront.icon name="search" class="h-5 w-5" />
        </span>

        <input type="search" name="q" x-model="query" x-ref="box"
               x-on:input="look()"
               x-on:focus="reopen()"
               x-on:keydown.arrow-down.prevent="move(1)"
               x-on:keydown.arrow-up.prevent="move(-1)"
               x-on:keydown.enter="choose($event)"
               autocomplete="off"
               aria-label="Search the shop"
               placeholder="Search for {{ $example ? '“'.$example.'”' : 'anything' }}"
               value="{{ $value }}"
               class="min-w-0 flex-1 border-0 bg-transparent py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">

        <span x-show="searching" x-cloak class="shrink-0 text-xs text-slate-400">…</span>

        <button type="submit" aria-label="Search"
                class="m-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white transition hover:opacity-90"
                style="background: {{ $accent }}">
            <x-storefront.icon name="search" class="h-4 w-4" />
        </button>
    </div>

    {{-- What matches, while they are still typing --}}
    <div x-show="open && (results.length || (! searching && canSearch))" x-cloak x-transition.opacity.duration.150ms
         class="absolute inset-x-0 top-full z-40 mt-2 overflow-hidden rounded-2xl bg-white text-start shadow-lg ring-1 ring-slate-100">

        <template x-if="! results.length && ! searching">
            <p class="px-4 py-3 text-sm text-slate-500">
                Nothing matched <span class="font-medium text-slate-700" x-text="query"></span>.
            </p>
        </template>

        <ul>
            <template x-for="(result, index) in results" :key="result.url">
                <li>
                    <a x-bind:href="result.url"
                       x-on:mouseenter="highlighted = index"
                       x-bind:class="highlighted === index ? 'bg-slate-50' : ''"
                       class="flex items-center gap-3 px-3 py-2">
                        <span class="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                            <template x-if="result.image">
                                <img x-bind:src="result.image" alt="" class="h-full w-full object-cover">
                            </template>
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm text-slate-800" x-text="result.name"></span>
                        <span class="shrink-0 text-end">
                            <span class="block text-sm font-semibold" style="color: {{ $accent }}" x-text="result.price"></span>
                            <template x-if="result.unit">
                                <span class="block text-[11px] text-slate-400" x-text="result.unit"></span>
                            </template>
                        </span>
                    </a>
                </li>
            </template>
        </ul>

        {{-- More than fits: the whole list is one press away --}}
        <template x-if="total > results.length">
            <button type="submit"
                    class="block w-full border-t border-slate-100 px-4 py-2.5 text-start text-sm font-medium hover:bg-slate-50"
                    style="color: {{ $accent }}">
                See all <span x-text="total"></span> matches
            </button>
        </template>
    </div>
</form>
