@props(['location', 'searchUrl', 'accent' => '#16a34a', 'tone' => 'light'])

<div x-data="shopperLocation(@js(['searchUrl' => $searchUrl]))" class="relative">
    <button type="button" x-on:click="show()"
            class="flex max-w-[16rem] items-center gap-2 rounded-full px-3 py-1.5 text-sm transition
                   {{ $tone === 'dark' ? 'bg-white/15 text-white hover:bg-white/25' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 21s7-5.686 7-11a7 7 0 1 0-14 0c0 5.314 7 11 7 11Z"/>
            <circle cx="12" cy="10" r="2.5"/>
        </svg>
        <span class="truncate">
            {{ $location->isSet() ? ($location->label() ?: 'Where you are') : 'Set where you are' }}
        </span>
        <svg class="h-3.5 w-3.5 shrink-0 opacity-60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>

    <!-- The panel -->
    <div x-show="open" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/40 p-4 pt-24"
         x-on:click.self="open = false" x-on:keydown.escape.window="open = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Where should we deliver?</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        We will only show you what this shop can actually get to you.
                    </p>
                </div>
                <button type="button" x-on:click="open = false" class="text-slate-400 hover:text-slate-700">&times;</button>
            </div>

            <div class="relative mt-4">
                <input type="search" x-ref="box" x-model="query" x-on:input="search()"
                       placeholder="Your area, road or landmark"
                       class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 focus:outline-none"
                       x-bind:style="{ borderColor: query ? '{{ $accent }}' : '' }">
                <span x-show="searching" x-cloak class="absolute end-3 top-3 text-xs text-slate-400">Looking…</span>
            </div>

            <ul x-show="results.length" x-cloak class="mt-2 max-h-60 space-y-1 overflow-auto">
                <template x-for="result in results" :key="result.name">
                    <li>
                        <button type="button" x-on:click="pick(result)"
                                class="block w-full rounded-lg px-3 py-2 text-start text-sm text-slate-700 hover:bg-slate-50"
                                x-text="result.name"></button>
                    </li>
                </template>
            </ul>

            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                <button type="button" x-on:click="here()"
                        class="rounded-xl px-4 py-2 text-sm font-medium text-white"
                        style="background: {{ $accent }}">
                    <span x-show="! locating">Use where I am now</span>
                    <span x-show="locating" x-cloak>Finding you…</span>
                </button>

                @if ($location->isSet())
                    <form method="POST" action="{{ route('storefront.location.forget') }}">
                        @csrf
                        <button type="submit" class="text-sm text-slate-500 hover:text-slate-900">Show me everything instead</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <form x-ref="form" method="POST" action="{{ route('storefront.location.store') }}" class="hidden">
        @csrf
        <input type="hidden" name="latitude" x-ref="latitude">
        <input type="hidden" name="longitude" x-ref="longitude">
        <input type="hidden" name="label" x-ref="label">
    </form>
</div>
