<div class="space-y-6">
    <div>
        <a href="{{ route('super.packages.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">&larr; Plans</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $package ? 'Edit '.$package->name : 'New plan' }}</h1>
        @if ($shopsOnPlan > 0)
            <p class="mt-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ number_format($shopsOnPlan) }} shop(s) are on this plan. Changing what it allows applies to them
                straight away. Changing the price does not: what they already agreed to pay stays as it is.
            </p>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">The plan</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" wire:model.blur="name" placeholder="Growth"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">One line about it</label>
                    <input type="text" wire:model="description" placeholder="For a shop selling every day."
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Address label</label>
                    <input type="text" wire:model="slug"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">Used in links. Letters, numbers and dashes.</p>
                    @error('slug') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Billed</label>
                    <select wire:model="billing_period"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="monthly">Every month</option>
                        <option value="yearly">Every year</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Free trial (days)</label>
                    <input type="number" min="0" max="365" wire:model="trial_days"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('trial_days') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Order on the pricing page</label>
                    <input type="number" min="0" max="999" wire:model="sort_order"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('sort_order') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                    On sale to new shops
                </label>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="is_public" class="rounded border-slate-300">
                    Show on the public pricing page
                </label>
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Price in each market</h2>
            <p class="mt-1 mb-4 text-sm text-slate-500">
                Set the price you want in each currency. Leave one empty and the plan is simply not sold there.
                Nothing is converted for you.
            </p>

            @error('prices') <p class="mb-3 text-sm text-rose-700">{{ $message }}</p> @enderror

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($currencies as $code => $currency)
                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            {{ $currency['name'] }}
                            <span class="font-normal text-slate-400">({{ $code }})</span>
                        </label>
                        <div class="flex items-center rounded-lg border border-slate-300 focus-within:border-slate-500">
                            <span class="px-3 text-sm text-slate-500">{{ $currency['symbol'] }}</span>
                            <input type="text" inputmode="decimal" wire:model="prices.{{ $code }}"
                                   placeholder="{{ $currency['exponent'] === 0 ? '149000' : '0.00' }}"
                                   class="w-full rounded-r-lg border-0 px-2 py-2 text-sm focus:outline-none">
                        </div>
                        @if ($currency['exponent'] === 0)
                            <p class="mt-1 text-xs text-slate-500">Whole numbers only — this currency has no decimals.</p>
                        @endif
                        @error('prices.'.$code) <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">What the plan allows</h2>
            <p class="mt-1 mb-4 text-sm text-slate-500">
                Leave a number empty for no limit at all. Put 0 to switch that thing off completely.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($features as $feature => $definition)
                    <div>
                        @if ($definition['type'] === 'limit')
                            <label class="mb-1 block text-sm font-medium">{{ $definition['label'] }}</label>
                            <input type="number" min="0" @if (isset($definition['max'])) max="{{ $definition['max'] }}" @endif
                                   wire:model="limits.{{ $feature }}" placeholder="{{ isset($definition['max']) ? 'Up to '.$definition['max'] : 'No limit' }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                            @error('limits.'.$feature) <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                        @else
                            <label class="flex h-full items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" wire:model="switches.{{ $feature }}" class="rounded border-slate-300">
                                {{ $definition['label'] }}
                            </label>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800">
                <span wire:loading.remove wire:target="save">Save plan</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            <a href="{{ route('super.packages.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">Cancel</a>
        </div>
    </form>
</div>
