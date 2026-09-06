<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Brands</h1>
        <p class="mt-1 text-sm text-slate-500">The makers whose things you sell.</p>
    </div>

    @if ($message !== '')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</div>
    @endif

    <form wire:submit="save" class="rounded-xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">
            {{ $editingId ? 'Edit brand' : 'New brand' }}
        </h2>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-48">
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input type="text" wire:model="name" placeholder="Aarong"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 py-2 text-sm">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                Shown in the shop
            </label>

            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                {{ $editingId ? 'Save changes' : 'Add brand' }}
            </button>

            @if ($editingId)
                <button type="button" wire:click="cancel" class="px-2 py-2 text-sm text-slate-500 hover:text-slate-900">Cancel</button>
            @endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">Brand</th>
                    <th class="px-5 py-3 text-start font-semibold">Products</th>
                    <th class="px-5 py-3 text-start font-semibold">Shown</th>
                    <th class="px-5 py-3 text-end font-semibold">&nbsp;</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($brands as $brand)
                    <tr>
                        <td class="px-5 py-3 font-medium">{{ $brand->name }}</td>
                        <td class="px-5 py-3 tabular-nums">{{ number_format($brand->products_count) }}</td>
                        <td class="px-5 py-3">{{ $brand->is_active ? 'Yes' : 'No' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex justify-end gap-2">
                                <button wire:click="edit({{ $brand->id }})"
                                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Edit</button>
                                <button wire:click="delete({{ $brand->id }})" wire:confirm="Delete {{ $brand->name }}?"
                                        class="rounded-lg border border-rose-200 px-3 py-1.5 text-sm text-rose-700 hover:bg-rose-50">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No brands yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
