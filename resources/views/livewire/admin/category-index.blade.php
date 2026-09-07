<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Categories</h1>
        <p class="mt-1 text-sm text-slate-500">How your shop is arranged. A category can sit inside another.</p>
    </div>

    <form wire:submit="save" class="card rise rise-1 p-6">
        <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">
            {{ $editingId ? 'Edit category' : 'New category' }}
        </h2>

        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-48">
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input type="text" wire:model="name" placeholder="Shirts"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Sits inside</label>
                <select wire:model="parent_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    <option value="">Nothing — top level</option>
                    @foreach ($categories as $category)
                        @continue($category->id === $editingId)
                        <option value="{{ $category->id }}">{{ $category->path() }}</option>
                    @endforeach
                </select>
                @error('parent_id') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 py-2 text-sm">
                <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                Shown in the shop
            </label>

            <button type="submit" class="btn btn-primary">
                {{ $editingId ? 'Save changes' : 'Add category' }}
            </button>

            @if ($editingId)
                <button type="button" wire:click="cancel" class="px-2 py-2 text-sm text-slate-500 hover:text-slate-900">Cancel</button>
            @endif
        </div>

        @php($existing = $editingId ? $categories->firstWhere('id', $editingId) : null)

        <div class="mt-5 border-t border-slate-100 pt-5">
            <label class="mb-1 block text-sm font-medium">Picture</label>
            <p class="mb-3 text-xs text-slate-500">
                Some shop looks show a picture for each category. A square photo works best.
                Without one, the first letter of the name is shown instead.
            </p>

            <div class="flex flex-wrap items-center gap-4">
                {{-- What is there now, or what is about to be --}}
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100">
                    @if ($photo && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="" class="h-full w-full object-cover">
                    @elseif ($photo)
                        <span class="px-1 text-center text-[10px] leading-tight text-rose-600">Not a picture</span>
                    @elseif ($existing?->hasImage() && ! $dropPhoto)
                        <img src="{{ $existing->thumbnailUrl() }}" alt="{{ $existing->name }}" class="h-full w-full object-cover">
                    @else
                        <span class="text-2xl font-semibold text-slate-400">
                            {{ mb_substr($name ?: '?', 0, 1) }}
                        </span>
                    @endif
                </div>

                <div class="flex-1 min-w-48">
                    <input type="file" wire:model="photo" accept="image/*"
                           class="block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0
                                  file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">

                    <div wire:loading wire:target="photo" class="mt-1 text-xs text-slate-500">Uploading…</div>
                    @error('photo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

                    @if ($existing?->hasImage() && ! $photo)
                        <label class="mt-2 flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="dropPhoto" class="rounded border-slate-300">
                            Remove the picture when I save
                        </label>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card rise rise-2 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">&nbsp;</th>
                    <th class="px-5 py-3 text-start font-semibold">Category</th>
                    <th class="px-5 py-3 text-start font-semibold">Products</th>
                    <th class="px-5 py-3 text-start font-semibold">Shown</th>
                    <th class="px-5 py-3 text-end font-semibold">&nbsp;</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr wire:key="cat-{{ $category->id }}">
                        <td class="py-3 ps-5">
                            <div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                                @if ($category->hasImage())
                                    <img src="{{ $category->thumbnailUrl() }}" alt="{{ $category->name }}"
                                         loading="lazy" class="h-full w-full object-cover">
                                @else
                                    <span class="text-sm font-semibold text-slate-400">{{ mb_substr($category->name, 0, 1) }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3 font-medium">{{ $category->path() }}</td>
                        <td class="px-5 py-3 tabular-nums">{{ number_format($category->products_count) }}</td>
                        <td class="px-5 py-3">{{ $category->is_active ? 'Yes' : 'No' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex justify-end gap-2">
                                <button wire:click="edit({{ $category->id }})"
                                        class="btn btn-quiet !px-3 !py-1.5">Edit</button>
                                @if ($category->hasImage())
                                    <button wire:click="removeImage({{ $category->id }})"
                                            wire:confirm="Remove the picture on {{ $category->name }}?"
                                            class="btn btn-quiet !px-3 !py-1.5">Remove picture</button>
                                @endif
                                <button wire:click="delete({{ $category->id }})" wire:confirm="Delete {{ $category->name }}?"
                                        class="btn !px-3 !py-1.5 border border-rose-200 text-rose-700 hover:bg-rose-50">Delete</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
