<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Add-ons</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Extras a shop can buy on top of its plan, so a shop that needs a little more does not have to
                jump a whole plan. Priced separately in every market, like plans are.
            </p>
        </div>
        @unless ($adding || $editingId)
            <button type="button" wire:click="add"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                + New add-on
            </button>
        @endunless
    </div>

    @if ($message !== '')
        <div class="rounded-xl px-4 py-3 text-sm
                    {{ $messageType === 'error' ? 'bg-rose-50 text-rose-900' : 'bg-emerald-50 text-emerald-900' }}">
            {{ $message }}
        </div>
    @endif

    {{-- The list --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        @if ($addons->isEmpty())
            <div class="p-10 text-center">
                <h2 class="text-base font-semibold">No add-ons yet</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                    A shop on Starter that needs 60 products has to pay for Growth. An add-on is how it stays.
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($addons as $addon)
                    <div wire:key="addon-{{ $addon->id }}" class="flex flex-wrap items-center gap-4 p-5">
                        <div class="min-w-48 flex-1">
                            <p class="font-semibold">
                                {{ $addon->name }}
                                @unless ($addon->is_active)
                                    <span class="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Off sale</span>
                                @endunless
                            </p>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $addon->what() }}</p>
                            <p class="mt-1 text-xs text-slate-400">
                                {{ $addon->prices->count() }} {{ $addon->prices->count() === 1 ? 'currency' : 'currencies' }}
                                · {{ ($sold[$addon->id] ?? 0) === 0
                                    ? 'no shops have it'
                                    : ($sold[$addon->id] === 1 ? '1 shop has it' : $sold[$addon->id].' shops have it') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            @foreach ($addon->prices->sortBy('currency') as $price)
                                <span class="rounded-lg bg-slate-50 px-2 py-1 text-xs tabular-nums text-slate-600">
                                    {{ $price->currency }} {{ $price->price->toDisplay() }}
                                </span>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            <button type="button" wire:click="toggle({{ $addon->id }})"
                                    class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50">
                                {{ $addon->is_active ? 'Take off sale' : 'Put on sale' }}
                            </button>
                            <button type="button" wire:click="edit({{ $addon->id }})"
                                    class="rounded-xl border border-slate-200 px-3 py-1.5 text-sm hover:bg-slate-50">
                                Edit
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Adding or changing one --}}
    @if ($adding || $editingId)
        <form wire:submit="save" class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="mb-4 font-semibold">{{ $editingId ? 'Change this add-on' : 'A new add-on' }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" wire:model="name" maxlength="80" placeholder="50 more products"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">A line about it (optional)</label>
                    <input type="text" wire:model="description" maxlength="160"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">What it adds to</label>
                    <select wire:model.live="feature"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                        @foreach ($features as $key => $definition)
                            <option value="{{ $key }}">
                                {{ $definition['label'] }} ({{ $definition['type'] === 'limit' ? 'counted' : 'on or off' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">How it is sold</label>
                    <select wire:model.live="kind"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                        <option value="units">More of it, a fixed amount at a time</option>
                        <option value="switch">Switched on</option>
                    </select>
                    @error('kind') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                @if ($kind === 'units')
                    <div>
                        <label class="mb-1 block text-sm font-medium">How much, each time it is bought</label>
                        <input type="number" wire:model="unit_amount" min="1"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">
                            A shop buying two of these gets twice this much.
                        </p>
                        @error('unit_amount') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex items-end">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                        On sale
                    </label>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="text-sm font-semibold">What it costs, in each market</h3>
                <p class="mt-0.5 text-sm text-slate-500">
                    Leave a currency empty and it is simply not sold there.
                </p>

                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    @foreach ($currencies as $code => $currency)
                        <div wire:key="price-{{ $code }}">
                            <label class="mb-1 block text-xs font-medium text-slate-500">
                                {{ $code }} · {{ $currency['name'] }}
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-500">{{ $currency['symbol'] }}</span>
                                <input type="text" inputmode="decimal" wire:model="prices.{{ $code }}"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm tabular-nums focus:border-blue-400 focus:outline-none">
                            </div>
                            @error('prices.'.$code) <p class="mt-1 text-xs text-rose-700">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <button type="submit"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Save
                </button>
                <button type="button" wire:click="cancel" class="text-sm text-slate-500 hover:text-slate-900">Cancel</button>
            </div>
        </form>
    @endif
</div>
