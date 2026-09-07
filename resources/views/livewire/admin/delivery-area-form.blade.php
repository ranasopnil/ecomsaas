<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Delivery areas</h1>
        <p class="mt-1 text-sm text-slate-500">
            Name the places you deliver to — Dhaka city, Mirpur, Uttara — and draw each one once.
            Then on every product you just tick the areas it goes to.
        </p>
    </div>

    @if ($areas->isEmpty() && ! $adding)
        <div class="card rise rise-1 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-700">✓</div>
            <h2 class="mt-4 text-lg font-semibold">You deliver everywhere</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                You have not named any areas, so every customer sees everything you sell, wherever they are.
                Add an area only if there are places you cannot reach.
            </p>
            <button type="button" wire:click="add" class="btn btn-primary mt-5 !px-4 !py-2">Add my first area</button>
        </div>
    @endif

    {{-- The list --}}
    @if ($areas->isNotEmpty())
        <div class="card rise rise-1 p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Where you deliver</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        A customer outside every one of these sees nothing that follows your shop.
                    </p>
                </div>
                @unless ($adding || $editingId)
                    <button type="button" wire:click="add" class="btn btn-primary !px-4 !py-2">+ Add an area</button>
                @endunless
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($areas as $area)
                    <div wire:key="area-{{ $area->id }}" class="flex flex-wrap items-center gap-3 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 21s7-5.686 7-11a7 7 0 1 0-14 0c0 5.314 7 11 7 11Z"/>
                                <circle cx="12" cy="10" r="2.5"/>
                            </svg>
                        </span>

                        <div class="min-w-40 flex-1">
                            <div class="font-medium">{{ $area->name }}</div>
                            <div class="text-xs text-slate-500">
                                Within {{ $area->distance() }} km ·
                                {{ $area->products_count === 0
                                    ? 'not picked on any product yet'
                                    : $area->products_count.' '.($area->products_count === 1 ? 'product' : 'products').' tied to it' }}
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" wire:click="edit({{ $area->id }})" class="btn btn-quiet !px-3 !py-1.5">Edit</button>
                            <button type="button" wire:click="delete({{ $area->id }})"
                                    wire:confirm="Remove {{ $area->name }}?"
                                    class="btn !px-3 !py-1.5 border border-rose-200 text-rose-700 hover:bg-rose-50">Remove</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Add or edit --}}
    @if ($adding || $editingId)
        <div wire:key="area-editor-{{ $editingId ?? 'new' }}" class="card rise rise-2 p-5">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">
                {{ $editingId ? 'Change this area' : 'New delivery area' }}
            </h2>

            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium">What do you call this area?</label>
                <input type="text" wire:model="name" placeholder="Mirpur"
                       class="w-full max-w-sm rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">
                @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium">What do you charge to deliver here?</label>
                <div class="flex max-w-[12rem] items-center rounded-xl border border-slate-200 focus-within:border-violet-400">
                    <span class="px-3 text-sm text-slate-500">{{ $currency }}</span>
                    <input type="text" inputmode="decimal" wire:model="charge" placeholder="0"
                           class="w-full rounded-e-xl border-0 px-2 py-2 text-sm focus:outline-none">
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Charged once per order going to this area. Leave it at 0 for free delivery.
                </p>
                @error('charge') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold">Where is it?</h3>
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
            @error('latitude') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

            <div class="mt-5 flex items-center gap-3 border-t border-slate-100 pt-5">
                <button type="button" wire:click="save" class="btn btn-primary !px-4 !py-2">
                    {{ $editingId ? 'Save changes' : 'Add this area' }}
                </button>
                <button type="button" wire:click="cancel" class="text-sm text-slate-500 hover:text-slate-900">Cancel</button>
            </div>
        </div>
    @endif

    {{-- What the products are doing --}}
    <div class="card rise rise-3 p-5">
        <h2 class="font-semibold">What your products do</h2>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Go wherever the shop goes</dt>
                <dd class="font-medium">{{ $followers }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Tied to particular areas</dt>
                <dd class="font-medium">{{ $tied }}</dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Delivered anywhere</dt>
                <dd class="font-medium">{{ $anywhere }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-slate-500">
            Change any single product on its own page, under "Where you deliver it".
        </p>
    </div>
</div>
