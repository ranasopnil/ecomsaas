<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Shops</h1>
        <p class="mt-1 text-sm text-slate-500">Every shop on the platform and the plan it is on.</p>
    </div>

    @if ($message !== '')
        <div class="rounded-lg border px-4 py-3 text-sm {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' }}">
            {{ $message }}
        </div>
    @endif

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search by shop name or address"
           class="w-full max-w-sm rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 font-semibold">Shop</th>
                    <th class="px-5 py-3 font-semibold">Currency</th>
                    <th class="px-5 py-3 font-semibold">Plan</th>
                    <th class="px-5 py-3 font-semibold">Paying</th>
                    <th class="px-5 py-3 font-semibold">Move to another plan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($stores as $store)
                    @php($subscription = $subscriptions[$store->id] ?? null)
                    <tr>
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ $store->name }}</div>
                            <div class="text-xs text-slate-500">{{ $store->slug }}{{ config('tenancy.subdomain_suffix') }}</div>
                        </td>
                        <td class="px-5 py-3">{{ $store->currency }}</td>
                        <td class="px-5 py-3">
                            @if ($subscription)
                                {{ $subscription->package?->name }}
                                <div class="text-xs text-slate-500">{{ $subscription->status }}</div>
                            @else
                                <span class="text-rose-700">No plan</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 tabular-nums">
                            {{ $subscription ? $subscription->currency.' '.$subscription->price->toDecimal() : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <select wire:model="planChoice.{{ $store->id }}"
                                        class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                    <option value="">Choose…</option>
                                    @foreach ($packages as $package)
                                        <option value="{{ $package->slug }}">{{ $package->name }}</option>
                                    @endforeach
                                </select>
                                <button wire:click="changePlan({{ $store->id }})"
                                        wire:confirm="Move {{ $store->name }} to the chosen plan? The current subscription ends today."
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">
                                    Move
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No shops found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $stores->links() }}
</div>
