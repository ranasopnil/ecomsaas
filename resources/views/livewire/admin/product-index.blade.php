<div class="space-y-6">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Products</h1>
            <p class="mt-1 text-sm text-slate-500">Everything your shop sells.</p>
        </div>
        <a href="{{ route('admin.products.create') }}" wire:navigate
           class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add a product</a>
    </div>

    @if ($message !== '')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</div>
    @endif

    <div class="flex flex-wrap gap-3">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search products"
               class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
        <select wire:model.live="status"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
            <option value="">Every state</option>
            <option value="draft">Still writing</option>
            <option value="active">On sale</option>
            <option value="archived">Put away</option>
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-start text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">Product</th>
                    <th class="px-5 py-3 text-start font-semibold">State</th>
                    <th class="px-5 py-3 text-start font-semibold">Price</th>
                    <th class="px-5 py-3 text-start font-semibold">You make</th>
                    <th class="px-5 py-3 text-start font-semibold">Stock</th>
                    <th class="px-5 py-3 text-end font-semibold">&nbsp;</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($products as $product)
                    @php($variant = $product->defaultVariant())
                    <tr>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.products.edit', $product) }}" wire:navigate class="font-medium hover:underline">
                                {{ $product->name }}
                            </a>
                            <div class="text-xs text-slate-500">
                                {{ $product->brand?->name }}
                                @if ($product->has_variants)
                                    · {{ $product->variants->count() }} choices
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            @php($badge = match ($product->status) {
                                'active' => 'bg-emerald-100 text-emerald-800',
                                'draft' => 'bg-amber-100 text-amber-800',
                                default => 'bg-slate-200 text-slate-700',
                            })
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $badge }}">
                                {{ ['draft' => 'Still writing', 'active' => 'On sale', 'archived' => 'Put away'][$product->status] }}
                            </span>
                        </td>
                        <td class="px-5 py-3 tabular-nums">
                            {{ $variant ? $variant->currency.' '.$variant->price->toDecimal() : '—' }}
                        </td>
                        <td class="px-5 py-3 tabular-nums">
                            @if ($variant?->marginPercent() !== null)
                                <span class="{{ $variant->profitMinor() < 0 ? 'text-rose-700' : '' }}">
                                    {{ number_format($variant->profitMinor() / (10 ** $variant->currency_exponent), $variant->currency_exponent) }}
                                </span>
                                <span class="text-xs text-slate-500">({{ $variant->marginPercent() }}%)</span>
                            @else
                                <span class="text-xs text-slate-400">No cost set</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 tabular-nums">
                            @php($stock = $product->variants->sum(fn ($v) => $v->inventory?->available ?? 0))
                            @php($tracked = $product->variants->contains(fn ($v) => $v->inventory?->track_inventory))
                            @if (! $tracked)
                                <span class="text-slate-500">Not counted</span>
                            @else
                                <span class="{{ $stock <= 0 ? 'text-rose-700 font-medium' : '' }}">{{ number_format($stock) }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('storefront.product', $product->slug) }}" target="_blank" rel="noopener"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50"
                                   title="See this product the way a customer does">View</a>
                                <a href="{{ route('admin.products.edit', $product) }}" wire:navigate
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Edit</a>
                                @if ($product->status === 'archived')
                                    <button wire:click="putBackOnSale({{ $product->id }})"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Put back</button>
                                @else
                                    <button wire:click="archive({{ $product->id }})"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Put away</button>
                                @endif
                                <button wire:click="delete({{ $product->id }})"
                                        wire:confirm="Delete {{ $product->name }}?"
                                        class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm text-rose-700 hover:bg-rose-50">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">
                        No products yet. Add your first one.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $products->links() }}
</div>
