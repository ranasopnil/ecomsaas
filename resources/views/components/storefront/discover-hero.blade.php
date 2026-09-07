@props([
    'store',
    'location',
    'searchUrl',
    'accent' => '#16a34a',
    'figures' => [],
])

{{--
    The front of the shop, before anything is sold.

    The browser is asked where the customer is as the page opens, so the usual
    visitor never types anything: the address simply appears with a pin beside
    it. Anyone who would rather shop for somewhere else types it here instead.
--}}
<section class="bg-slate-50 pb-10 pt-6">
    <div class="mx-auto max-w-6xl px-4">

        <div x-data="shopperLocation(@js([
                'searchUrl' => $searchUrl,
                'auto' => ! $location->isSet(),
                'known' => $location->isSet(),
            ]))"
             class="relative overflow-hidden rounded-3xl px-5 py-12 text-center sm:px-10 sm:py-16"
             style="background:
                 radial-gradient(120% 120% at 50% 0%, {{ $accent }}1f 0%, {{ $accent }}0d 42%, rgba(255,255,255,0) 72%),
                 linear-gradient(180deg, #f2faf5 0%, #f7fbf8 100%)">

            <h1 class="mx-auto max-w-3xl text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
                Your Everyday <span style="color: {{ $accent }}">Needs,</span> Delivered
                <span style="color: {{ $accent }}">Fast</span>
            </h1>

            <p class="mx-auto mt-4 max-w-2xl text-sm text-slate-500 sm:text-base">
                Enter your address to enjoy fast delivery of groceries and daily needs from {{ $store->name }}.
            </p>

            <p class="mt-6 text-base font-medium text-slate-700 sm:text-lg">
                @if ($location->isSet())
                    Showing what reaches you
                @else
                    Discover everything you need near you
                @endif
            </p>

            {{-- Where the customer is, once we know --}}
            @if ($location->isSet())
                <p class="mx-auto mt-3 flex max-w-xl items-center justify-center gap-2 text-sm font-medium"
                   style="color: {{ $accent }}">
                    <x-storefront.icon name="pin" class="h-4 w-4 shrink-0" />
                    <span class="truncate">{{ $location->label() ?: 'Where you are' }}</span>
                </p>
            @endif

            {{-- The location box --}}
            <div class="relative mx-auto mt-5 max-w-2xl">
                <div class="flex items-center gap-1 rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-slate-100">
                    <input type="search" x-ref="box" x-model="query" x-on:input="search()"
                           x-on:keydown.enter.prevent="discover()"
                           placeholder="{{ $location->isSet() ? 'Search another location…' : 'Search location here…' }}"
                           aria-label="Search location"
                           class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0">

                    <span x-show="searching" x-cloak class="shrink-0 px-1 text-xs text-slate-400">Looking…</span>

                    <button type="button" x-on:click="here()" title="Use where I am now"
                            aria-label="Use where I am now"
                            class="shrink-0 rounded-lg p-2 transition hover:bg-slate-50"
                            style="color: {{ $accent }}">
                        <x-storefront.icon name="crosshair" class="h-5 w-5"
                                           x-bind:class="locating ? 'animate-pulse' : ''" />
                    </button>

                    @if ($location->isSet())
                        {{-- They are known: the button opens the shop unless they are typing somewhere new --}}
                        <button type="button"
                                x-on:click="canDiscover ? discover() : (window.location.href = '{{ route('storefront.browse') }}')"
                                class="shrink-0 rounded-lg px-5 py-2.5 text-sm font-medium text-white transition"
                                style="background: {{ $accent }}"
                                x-text="canDiscover ? 'Discover' : 'Browse'">Browse</button>
                    @else
                        <button type="button" x-on:click="discover()"
                                x-bind:disabled="! canDiscover"
                                class="shrink-0 rounded-lg px-5 py-2.5 text-sm font-medium transition
                                       disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400"
                                x-bind:style="canDiscover ? 'background: {{ $accent }}; color: #fff' : ''">
                            Discover
                        </button>
                    @endif
                </div>

                {{-- Matches for what they typed --}}
                <ul x-show="results.length" x-cloak x-transition.opacity
                    class="absolute inset-x-0 top-full z-30 mt-2 max-h-64 overflow-auto rounded-xl bg-white p-1.5 text-start shadow-lg ring-1 ring-slate-100">
                    <template x-for="result in results" :key="result.name">
                        <li>
                            <button type="button" x-on:click="pick(result)"
                                    class="flex w-full items-start gap-2 rounded-lg px-3 py-2 text-start text-sm text-slate-700 hover:bg-slate-50">
                                <x-storefront.icon name="pin" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                <span x-text="result.name"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Only shown if the browser refused, so nobody is left stuck --}}
            <p x-show="refused" x-cloak class="mt-3 text-xs text-slate-500">
                <span x-show="blocked">
                    Location is switched off for this site in your browser. Click the lock or
                    settings icon in the address bar to allow it, then press the target button again.
                </span>
                <span x-show="! blocked">
                    We could not find where you are. Type your area above instead.
                </span>
            </p>

            @if ($location->isSet())
                <form method="POST" action="{{ route('storefront.location.forget') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-slate-500 underline-offset-4 hover:text-slate-900 hover:underline">
                        Show me everything instead
                    </button>
                </form>
            @endif

            <form x-ref="form" method="POST" action="{{ route('storefront.location.store') }}" class="hidden">
                @csrf
                <input type="hidden" name="latitude" x-ref="latitude">
                <input type="hidden" name="longitude" x-ref="longitude">
                <input type="hidden" name="label" x-ref="label">
            </form>
        </div>

        {{-- The shop's own real figures --}}
        @if (! empty($figures))
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'][count($figures)] ?? 'lg:grid-cols-4' }}">
                @foreach ($figures as $figure)
                    <div class="flex items-center gap-4 rounded-xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-100">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg"
                              style="background: {{ $accent }}1a; color: {{ $accent }}">
                            <x-storefront.icon :name="$figure['icon']" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-xl font-bold text-slate-900">{{ $figure['value'] }}</span>
                            <span class="block truncate text-[11px] font-medium uppercase tracking-wider text-slate-400">
                                {{ $figure['caption'] }}
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
