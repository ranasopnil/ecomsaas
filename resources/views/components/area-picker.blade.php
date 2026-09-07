@props([
    'provider' => 'osm',
    'latitude' => null,
    'longitude' => null,
    'radius' => 5,
    'paths' => [],
    'centre' => [23.8103, 90.4125],
    'zoom' => 11,
    'googleKey' => '',
    'accent' => '#16a34a',
])

<div
    x-data="areaPicker(@js([
        'provider' => $provider,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'radius' => $radius,
        'paths' => $paths,
        'centre' => $centre,
        'zoom' => $zoom,
        'googleKey' => $googleKey,
        'searchUrl' => route('admin.places.search'),
    ]))"
    wire:ignore
    class="space-y-3"
>
    <div class="relative">
        <input type="search" x-model="query" x-on:input="search()"
               placeholder="Search for a place — try your area or road name"
               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">

        <div x-show="searching" x-cloak class="absolute end-3 top-2.5 text-xs text-slate-400">Looking…</div>

        <ul x-show="results.length" x-cloak
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg">
            <template x-for="result in results" :key="result.name">
                <li>
                    <button type="button" x-on:click="pick(result)"
                            class="block w-full px-3 py-2 text-start text-sm hover:bg-slate-50" x-text="result.name"></button>
                </li>
            </template>
        </ul>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200">
        <div x-ref="canvas" class="h-72 w-full bg-slate-100"></div>
    </div>

    <p x-show="failed" x-cloak class="text-xs text-rose-600">
        The map did not load. Check the connection and reload the page.
    </p>

    <div class="flex flex-wrap items-center gap-3">
        <div class="flex flex-1 items-center gap-3">
            <label class="whitespace-nowrap text-xs font-medium text-slate-500">How far you deliver</label>
            <input type="range" min="0.5" max="100" step="0.5" x-model.number="radius" class="flex-1 accent-emerald-600">
            <span class="w-20 text-sm font-medium" x-text="radius + ' km'"></span>
        </div>

        <button type="button" x-on:click="here()" class="btn btn-quiet !px-3 !py-1.5">Use where I am</button>
        <button type="button" x-on:click="clear()" x-show="hasPoint" x-cloak
                class="text-sm text-slate-500 hover:text-slate-900">Clear the pin</button>
    </div>

    <p class="text-xs text-slate-500">
        <template x-if="hasPoint">
            <span>The green circle is your delivery area. Drag the pin or click the map to move it.</span>
        </template>
        <template x-if="! hasPoint">
            <span>Click the map, or search above, to drop a pin where you deliver from.</span>
        </template>
    </p>
</div>
