<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Stock</h1>
        <p class="mt-1 text-sm text-slate-500">
            Type the number you actually counted and press Save. Every change is written down.
        </p>
    </div>

    @if ($message !== '')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search products"
               class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="onlyProblems" class="rounded border-slate-300">
            Only what is out or running low
        </label>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">Product</th>
                    <th class="px-5 py-3 text-start font-semibold">Code</th>
                    <th class="px-5 py-3 text-start font-semibold">Available</th>
                    <th class="px-5 py-3 text-start font-semibold">Held for orders</th>
                    <th class="px-5 py-3 text-start font-semibold">Counted</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($variants as $variant)
                    @php($level = $variant->inventory)
                    <tr>
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ $variant->product->name }}</div>
                            <div class="text-xs text-slate-500">{{ $variant->choiceLabel() }}</div>
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ $variant->sku ?: '—' }}</td>
                        <td class="px-5 py-3 tabular-nums">
                            @if (! $level?->track_inventory)
                                <span class="text-slate-500">Not counted</span>
                            @else
                                <span class="{{ ($level->available ?? 0) <= 0 ? 'font-medium text-rose-700' : '' }}">
                                    {{ number_format($level->available) }}
                                </span>
                                @if ($level->isLow())
                                    <span class="ms-2 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Running low</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-5 py-3 tabular-nums text-slate-500">{{ number_format($level?->reserved ?? 0) }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <input type="number" wire:model="counted.{{ $variant->id }}" placeholder="—"
                                       class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                <button wire:click="saveCount({{ $variant->id }})"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Save</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">Nothing to count yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $variants->links() }}
</div>
