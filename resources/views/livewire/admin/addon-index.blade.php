@php
    use App\Models\SubscriptionAddon;

    $symbol = config('currencies.'.$shop->currency.'.symbol', $shop->currency.' ');
@endphp

<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Add-ons</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-500">
            A little more of something, without moving to a bigger plan. Everything you add renews on the
            same date as your plan, so there is still one date and one amount to think about.
        </p>
    </div>

    @error('buying')
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }}</div>
    @enderror

    {{-- What this shop already has --}}
    @if ($mine->isNotEmpty())
        <div class="card rise rise-1 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">What you have added</h2>

            <div class="mt-3 divide-y divide-slate-100">
                @foreach ($mine as $bought)
                    <div wire:key="mine-{{ $bought->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="font-medium">
                                {{ $bought->addon?->what() }}{{ $bought->quantity > 1 ? ' ×'.$bought->quantity : '' }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $symbol }}{{ $bought->total()->toDisplay() }} each renewal
                                @if ($bought->addon?->name) · {{ $bought->addon->name }} @endif
                            </p>
                        </div>

                        @if ($bought->status === SubscriptionAddon::STATUS_PENDING)
                            <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">
                                Waiting for your payment
                            </span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                                On
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($mine->contains(fn ($bought) => $bought->status === SubscriptionAddon::STATUS_PENDING))
                <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Something here is waiting on a payment. Send it and
                    <a href="{{ route('admin.plan.index') }}" wire:navigate class="font-semibold underline">
                        tell us on your plan page</a>, and it switches on as soon as we find it.
                </p>
            @endif
        </div>
    @endif

    {{-- What is on sale --}}
    @if ($addons->isEmpty())
        <div class="card rise rise-1 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-2xl text-rose-700">+</div>
            <h2 class="mt-4 text-lg font-semibold">Nothing to add just yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                There are no extras on sale for your shop at the moment. If you have run out of room on your
                plan, you can always
                <a href="{{ route('admin.plan.index') }}" wire:navigate class="font-medium text-rose-700 hover:underline">
                    move to a bigger one</a>.
            </p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($addons as $addon)
                @php($price = $addon->priceIn($shop->currency))
                <div wire:key="addon-{{ $addon->id }}" class="card rise rise-2 flex flex-col p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold">{{ $addon->name }}</h3>
                            <p class="mt-0.5 text-sm text-slate-500">{{ $addon->what() }}</p>
                        </div>
                        <p class="shrink-0 text-end">
                            <span class="text-lg font-bold tabular-nums">{{ $symbol }}{{ $price->toDisplay() }}</span>
                            <span class="block text-xs text-slate-500">a month</span>
                        </p>
                    </div>

                    @if ($addon->description)
                        <p class="mt-2 text-sm text-slate-600">{{ $addon->description }}</p>
                    @endif

                    <div class="mt-4 flex-1"></div>

                    @if ($buying === $addon->id)
                        <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                            @if ($addon->isUnits())
                                <label class="text-xs text-slate-500">How many</label>
                                <select wire:model="quantity"
                                        class="rounded-xl border border-slate-200 px-2 py-1.5 text-sm">
                                    @foreach (range(1, 5) as $n)
                                        <option value="{{ $n }}">
                                            ×{{ $n }} — {{ $symbol }}{{ $price->times($n)->toDisplay() }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif

                            <button type="button" wire:click="buy({{ $addon->id }})" class="btn btn-primary !px-4 !py-2">
                                Add it
                            </button>
                            <button type="button" wire:click="start({{ $addon->id }})"
                                    class="text-sm text-slate-500 hover:text-slate-900">Never mind</button>
                        </div>
                    @else
                        <button type="button" wire:click="start({{ $addon->id }})"
                                class="btn btn-quiet w-full !px-4 !py-2">
                            Add this
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="text-sm text-slate-500">
            Nothing switches on until we have found your payment. When you add something, send the money the
            usual way and
            <a href="{{ route('admin.plan.index') }}" wire:navigate class="font-medium text-rose-700 hover:underline">
                tell us on your plan page</a>.
        </p>
    @endif
</div>
