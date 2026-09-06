<div class="space-y-6">
    @if ($message !== '')
        <div class="rounded-lg border px-4 py-3 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' }}">
            {{ $message }}
        </div>
    @endif

    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Plans</h1>
            <p class="mt-1 text-sm text-slate-500">What merchants can subscribe to, and what each plan allows.</p>
        </div>
        <a href="{{ route('super.packages.create') }}" wire:navigate
           class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
            New plan
        </a>
    </div>

    @if ($packages->isEmpty())
        <div class="rounded-xl bg-white p-8 text-center shadow-sm">
            <p class="text-sm text-slate-500">There are no plans yet. Create the first one.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($packages as $package)
                <div class="rounded-xl bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold">{{ $package->name }}</h2>
                                @unless ($package->is_active)
                                    <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Not on sale</span>
                                @endunless
                                @unless ($package->is_public)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Hidden</span>
                                @endunless
                            </div>
                            <p class="mt-1 text-sm text-slate-500">{{ $package->description }}</p>
                            <p class="mt-2 text-sm text-slate-500">
                                {{ number_format($shopsPerPackage[$package->id] ?? 0) }} shop(s) on this plan ·
                                {{ $package->trial_days }} day trial · billed {{ $package->billing_period }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('super.packages.edit', $package) }}" wire:navigate
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Edit</a>
                            <button wire:click="toggleActive({{ $package->id }})"
                                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
                                {{ $package->is_active ? 'Stop selling' : 'Sell again' }}
                            </button>
                            <button wire:click="delete({{ $package->id }})"
                                    wire:confirm="Delete the {{ $package->name }} plan? This cannot be undone."
                                    class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm text-rose-700 hover:bg-rose-50">
                                Delete
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-6 border-t border-slate-100 pt-4 sm:grid-cols-2">
                        <div>
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Price by market</h3>
                            @if ($package->prices->isEmpty())
                                <p class="text-sm text-rose-700">No price set. No shop can buy this plan.</p>
                            @else
                                <ul class="space-y-1 text-sm">
                                    @foreach ($package->prices->sortBy('currency') as $price)
                                        <li class="flex justify-between">
                                            <span class="text-slate-500">{{ config('currencies.'.$price->currency.'.name', $price->currency) }}</span>
                                            <span class="tabular-nums font-medium">{{ $price->currency }} {{ $price->price->toDecimal() }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                                @php($missing = $package->missingCurrencies())
                                @if ($missing)
                                    <p class="mt-2 text-xs text-amber-700">No price yet in: {{ implode(', ', $missing) }}</p>
                                @endif
                            @endif
                        </div>

                        <div>
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">What it allows</h3>
                            <ul class="space-y-1 text-sm">
                                @foreach (config('features') as $feature => $definition)
                                    @php($entitlement = $package->entitlements->firstWhere('feature', $feature))
                                    <li class="flex justify-between">
                                        <span class="text-slate-500">{{ $definition['label'] }}</span>
                                        <span class="font-medium">
                                            @if ($definition['type'] === 'switch')
                                                {{ $entitlement?->enabled ? 'Yes' : 'No' }}
                                            @elseif ($entitlement === null)
                                                —
                                            @elseif ($entitlement->limit_value === null)
                                                Unlimited
                                            @else
                                                {{ number_format($entitlement->limit_value) }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
