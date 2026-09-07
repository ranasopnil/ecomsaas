<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Delivery area</h1>
        <p class="mt-1 text-sm text-slate-500">
            Where your shop delivers. Every product uses this unless you give that product an area of its own.
            Customers outside it do not see those products at all.
        </p>
    </div>

    <div class="card rise rise-1 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold">Do you deliver everywhere?</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Say yes if you post orders anywhere in the country. Say no if you deliver around one place.
                </p>
            </div>

            <button type="button" wire:click="$set('everywhere', {{ $everywhere ? 'false' : 'true' }})"
                    class="inline-flex h-7 w-12 shrink-0 items-center rounded-full p-0.5 transition
                           {{ $everywhere ? 'justify-end bg-emerald-500' : 'justify-start bg-slate-300' }}">
                <span class="h-6 w-6 rounded-full bg-white shadow"></span>
            </button>
        </div>

        @if ($everywhere)
            <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                Every customer sees everything you sell, wherever they are.
            </p>
        @else
            <div class="mt-5 border-t border-slate-100 pt-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">Where do you deliver from?</h3>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $mapName }}</span>
                </div>

                <x-area-picker
                    :provider="$provider"
                    :latitude="$latitude"
                    :longitude="$longitude"
                    :radius="$radius"
                    :centre="$centre"
                    :zoom="$zoom"
                    :google-key="$googleKey"
                    :paths="['latitude' => 'latitude', 'longitude' => 'longitude', 'radius' => 'radius']"
                />
            </div>
        @endif

        <div class="mt-5 flex items-center gap-3 border-t border-slate-100 pt-5">
            <button type="button" wire:click="save" class="btn btn-primary !px-4 !py-2">Save</button>
            <span class="text-sm text-slate-500">Takes effect on your shop straight away.</span>
        </div>
    </div>

    <div class="card rise rise-2 p-5">
        <h2 class="font-semibold">What your products do</h2>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Follow this shop area</dt>
                <dd class="font-medium">{{ $followers }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Have an area of their own</dt>
                <dd class="font-medium">{{ $ownArea }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Delivered anywhere</dt>
                <dd class="font-medium">{{ $anywhere }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-slate-500">
            You can change any single product on its own page, under "Where you deliver it".
        </p>
    </div>
</div>
