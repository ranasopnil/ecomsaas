<div class="mx-auto max-w-3xl space-y-5">

    <div>
        <a href="{{ route('super.stores.index') }}" wire:navigate
           class="text-xs font-semibold text-blue-600 hover:text-blue-700">← Back to shops</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">Add a shop</h1>
        <p class="mt-1 text-sm text-slate-500">
            This opens the shop, gives it its free web address, puts it on a plan and makes the owner's sign-in.
        </p>
    </div>

    @if ($madeName !== null)
        <div class="card rise border-emerald-200 bg-emerald-50/70 p-5">
            <p class="text-sm font-semibold text-emerald-900">{{ $madeName }} is open.</p>
            <p class="mt-1 text-sm text-emerald-800">
                Its address is
                <a href="{{ $madeAddress }}" target="_blank" rel="noopener" class="font-semibold underline">{{ $madeAddress }}</a>.
                The owner signs in at <span class="font-semibold">{{ $madeAddress }}/admin/login</span> with the
                password you just typed — tell them, and ask them to change it.
            </p>
        </div>
    @endif

    <form wire:submit="save" class="card p-6">
        <h2 class="font-semibold">The shop</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="text-sm font-medium">Shop name</span>
                <input type="text" wire:model.live="name" placeholder="Dhaka Fashion"
                       class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                @error('name') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block sm:col-span-2">
                <span class="text-sm font-medium">Web address</span>
                <div class="mt-1 flex items-center rounded-xl border border-slate-200 focus-within:border-blue-400">
                    <input type="text" wire:model="slug" placeholder="{{ $suggestion ?: 'dhaka-fashion' }}"
                           class="w-full rounded-s-xl px-3 py-2.5 text-sm focus:outline-none">
                    <span class="whitespace-nowrap px-3 text-sm text-slate-400">{{ config('tenancy.subdomain_suffix') }}</span>
                </div>
                <span class="mt-1 block text-xs text-slate-500">
                    Leave it empty and the shop name is used.
                </span>
                @error('slug') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium">Country</span>
                <select wire:model.live="country"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                    @foreach ($countries as $code => $country)
                        <option value="{{ $code }}">{{ $country['name'] }}</option>
                    @endforeach
                </select>
                @error('country') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium">Money it takes</span>
                <select wire:model="currency"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                    @foreach ($currencies as $code => $currency)
                        <option value="{{ $code }}">{{ $code }} — {{ $currency['name'] }}</option>
                    @endforeach
                </select>
                @error('currency') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block sm:col-span-2">
                <span class="text-sm font-medium">Plan</span>
                <select wire:model="plan"
                        class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                    @foreach ($packages as $package)
                        <option value="{{ $package->slug }}">
                            {{ $package->name }}
                            @if ($package->trial_days > 0) — {{ $package->trial_days }}-day trial @endif
                        </option>
                    @endforeach
                </select>
                @error('plan') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <h2 class="mt-8 font-semibold">Who owns it</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            Their sign-in for the shop's own dashboard. Nobody here can read the password back afterwards, so
            write it down before you press the button.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium">Their name</span>
                <input type="text" wire:model="ownerName"
                       class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                @error('ownerName') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium">Their email</span>
                <input type="email" wire:model="ownerEmail"
                       class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                @error('ownerEmail') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block sm:col-span-2">
                <span class="text-sm font-medium">A password for them</span>
                <input type="text" wire:model="ownerPassword" autocomplete="off"
                       class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                <span class="mt-1 block text-xs text-slate-500">At least twelve characters.</span>
                @error('ownerPassword') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Open the shop</span>
                <span wire:loading wire:target="save">Opening…</span>
            </button>
            <a href="{{ route('super.stores.index') }}" wire:navigate class="btn btn-quiet">Cancel</a>
        </div>
    </form>
</div>
