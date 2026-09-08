<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Couriers</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-500">
            Who you hand parcels to. Name them once here, then pick one from the list on every order
            instead of typing it again.
        </p>
    </div>

    @if ($couriers->isEmpty() && ! $adding)
        <div class="card rise rise-1 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-violet-100 text-2xl text-violet-700">🛵</div>
            <h2 class="mt-4 text-lg font-semibold">No couriers yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                You need at least one before you can hand an order over. Add the ones most shops here use,
                or name your own — your own delivery man counts.
            </p>
            <div class="mt-5 flex flex-wrap justify-center gap-2">
                @if ($suggestions !== [])
                    <button type="button" wire:click="addSuggested" class="btn btn-primary !px-4 !py-2">
                        Add the usual ones
                    </button>
                @endif
                <button type="button" wire:click="add" class="btn btn-quiet !px-4 !py-2">Add my own</button>
            </div>
        </div>
    @endif

    @if ($couriers->isNotEmpty())
        <div class="card rise rise-1 p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Your couriers</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Only the ones switched on are offered when you hand an order over.
                    </p>
                </div>
                @unless ($adding || $editingId)
                    <button type="button" wire:click="add" class="btn btn-primary !px-4 !py-2">+ Add a courier</button>
                @endunless
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($couriers as $courier)
                    <div wire:key="courier-{{ $courier->id }}" class="flex flex-wrap items-center gap-3 py-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold
                                     {{ $courier->is_active ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-400' }}">
                            {{ mb_substr($courier->name, 0, 1) }}
                        </span>

                        <div class="min-w-40 flex-1">
                            <div class="font-medium {{ $courier->is_active ? '' : 'text-slate-400' }}">
                                {{ $courier->name }}
                                @unless ($courier->is_active)
                                    <span class="ms-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Off</span>
                                @endunless
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                @if ($courier->isAutomatic())
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 font-medium text-violet-800">
                                        Books parcels itself
                                    </span>
                                    @unless ($courier->isComplete())
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-rose-800">Details missing</span>
                                    @endunless
                                    @if ($courier->isTestMode())
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">Test system</span>
                                    @endif
                                @elseif ($courier->canBeAutomatic())
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5">Numbers typed by hand</span>
                                @endif
                                <span>{{ $courier->phone ?: 'No phone number' }}</span>
                                <span>· {{ $courier->orders_count === 0
                                    ? 'no parcels yet'
                                    : $courier->orders_count.' '.($courier->orders_count === 1 ? 'parcel' : 'parcels') }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($courier->canBeAutomatic() && $courier->isComplete())
                                <button type="button" wire:click="test({{ $courier->id }})"
                                        wire:loading.attr="disabled" wire:target="test({{ $courier->id }})"
                                        class="btn btn-quiet !px-3 !py-1.5">
                                    <span wire:loading.remove wire:target="test({{ $courier->id }})">Test connection</span>
                                    <span wire:loading wire:target="test({{ $courier->id }})">Checking…</span>
                                </button>
                            @endif
                            <button type="button" wire:click="toggle({{ $courier->id }})" class="btn btn-quiet !px-3 !py-1.5">
                                {{ $courier->is_active ? 'Switch off' : 'Switch on' }}
                            </button>
                            <button type="button" wire:click="edit({{ $courier->id }})" class="btn btn-quiet !px-3 !py-1.5">Edit</button>
                            @if ($courier->orders_count === 0)
                                <button type="button" wire:click="delete({{ $courier->id }})"
                                        wire:confirm="Remove {{ $courier->name }}?"
                                        class="btn btn-quiet !px-3 !py-1.5 !text-rose-700">Remove</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($suggestions !== [])
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <button type="button" wire:click="addSuggested" class="text-sm text-violet-700 hover:underline">
                        Add any of the usual ones I am missing
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Adding or changing one --}}
    @if ($adding || $editingId)
        <form wire:submit="save" class="card rise rise-2 p-5">
            <h2 class="mb-4 font-semibold">{{ $editingId ? 'Change this courier' : 'Add a courier' }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" wire:model="name" maxlength="80" placeholder="Pathao Courier"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Phone (optional)</label>
                    <input type="text" wire:model="phone" maxlength="40" placeholder="+880 1712 345678"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    @error('phone') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">How do you work with them?</label>
                    <select wire:model.live="driver"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        <option value="">I book parcels with them myself, and type the number in</option>
                        @foreach ($modules as $key => $module)
                            <option value="{{ $key }}">{{ $module['name'] }} — this shop can talk to them</option>
                        @endforeach
                    </select>
                    @if ($driver !== '')
                        <p class="mt-1 text-xs text-slate-500">{{ $modules[$driver]['blurb'] ?? '' }}</p>
                    @endif
                </div>

                @if ($driver !== '')
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Booking parcels</label>
                        <div class="space-y-2">
                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                                <input type="radio" wire:model.live="mode" value="manual" class="mt-0.5">
                                <span>
                                    <span class="font-medium">By hand</span>
                                    <span class="block text-xs text-slate-500">
                                        You book the parcel with {{ $modules[$driver]['name'] ?? 'them' }} yourself and
                                        type the consignment number into the order. Nothing to set up.
                                    </span>
                                </span>
                            </label>
                            <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                                <input type="radio" wire:model.live="mode" value="automatic" class="mt-0.5">
                                <span>
                                    <span class="font-medium">Automatically</span>
                                    <span class="block text-xs text-slate-500">
                                        This shop books the parcel as you hand the order over, and the consignment
                                        number comes back on its own. Needs your own
                                        {{ $modules[$driver]['name'] ?? '' }} account details below.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    @if ($mode === 'automatic')
                        @foreach ($this->fields() as $fieldKey => $field)
                            <div wire:key="courier-field-{{ $driver }}-{{ $fieldKey }}"
                                 class="{{ ($field['type'] ?? 'text') === 'checkbox' ? 'sm:col-span-2' : '' }}">
                                @if (($field['type'] ?? 'text') === 'checkbox')
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" wire:model="form.{{ $fieldKey }}" class="rounded border-slate-300">
                                        {{ $field['label'] }}
                                    </label>
                                @else
                                    <label class="mb-1 block text-sm font-medium">{{ $field['label'] }}</label>
                                    <input type="{{ $field['type'] === 'password' ? 'password' : 'text' }}"
                                           wire:model="form.{{ $fieldKey }}"
                                           autocomplete="{{ $field['secret'] ? 'new-password' : 'off' }}"
                                           placeholder="{{ $field['secret'] && $editing?->hasSecret($fieldKey) ? '•••••••• saved — type to replace' : '' }}"
                                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                                    @if ($field['hint'] ?? false)
                                        <p class="mt-1 text-xs text-slate-500">{{ $field['hint'] }}</p>
                                    @elseif ($field['secret'])
                                        <p class="mt-1 text-xs text-slate-500">
                                            Stored encrypted and never shown again. Leave empty to keep what is saved.
                                        </p>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    @endif
                @endif

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Where a parcel can be followed (optional)</label>
                    <input type="text" wire:model="tracking_url" maxlength="300"
                           placeholder="https://steadfast.com.bd/t/{code}"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-mono text-xs focus:border-violet-400 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">
                        Put <span class="font-mono">{code}</span> where the consignment number goes. The customer gets a
                        link to it on their order.
                    </p>
                    @error('tracking_url') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" wire:click="cancel" class="text-sm text-slate-500 hover:text-slate-900">Cancel</button>
            </div>
        </form>
    @endif
</div>
