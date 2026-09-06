<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('admin.products.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">&larr; Products</a>
            <h1 class="mt-1 text-2xl font-semibold">{{ $product ? 'Edit '.$product->name : 'New product' }}</h1>
        </div>

        @if ($product)
            <a href="{{ route('storefront.product', $product->slug) }}" target="_blank" rel="noopener"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">
                View as a customer &nearr;
            </a>
        @endif
    </div>

    @if ($message !== '')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">The product</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Name</label>
                    <input type="text" wire:model.blur="name" placeholder="Cotton Panjabi"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Description</label>
                    <x-html-editor state="description" :value="$description" :images="$editorImages" />
                    @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Brand</label>
                    <select wire:model="brand_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="">No brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">State</label>
                    <select wire:model="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <option value="draft">Still writing</option>
                        <option value="active">On sale</option>
                        <option value="archived">Put away</option>
                    </select>
                </div>

                @if ($categories->isNotEmpty())
                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-medium">Categories</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($categories as $category)
                                <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm">
                                    <input type="checkbox" value="{{ $category->id }}" wire:model="category_ids" class="rounded border-slate-300">
                                    {{ $category->path() }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">
                Price and stock @if ($product?->has_variants) <span class="normal-case text-slate-400">(the starting point for each choice)</span> @endif
            </h2>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">Price</label>
                    <div class="flex items-center rounded-lg border border-slate-300 focus-within:border-slate-500">
                        <span class="px-3 text-sm text-slate-500">{{ $currency }}</span>
                        <input type="text" inputmode="decimal" wire:model="price"
                               class="w-full rounded-e-lg border-0 px-2 py-2 text-sm focus:outline-none">
                    </div>
                    @error('price') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Was (optional)</label>
                    <div class="flex items-center rounded-lg border border-slate-300 focus-within:border-slate-500">
                        <span class="px-3 text-sm text-slate-500">{{ $currency }}</span>
                        <input type="text" inputmode="decimal" wire:model="compare_at_price"
                               class="w-full rounded-e-lg border-0 px-2 py-2 text-sm focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Shown crossed out next to the price.</p>
                    @error('compare_at_price') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">What it costs you</label>
                    <div class="flex items-center rounded-lg border border-slate-300 focus-within:border-slate-500">
                        <span class="px-3 text-sm text-slate-500">{{ $currency }}</span>
                        <input type="text" inputmode="decimal" wire:model.blur="cost_price"
                               class="w-full rounded-e-lg border-0 px-2 py-2 text-sm focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        What you paid for it. Only you see this — it works out your profit.
                    </p>
                    @error('cost_price') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Your code (optional)</label>
                    <input type="text" wire:model="sku"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('sku') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                @unless ($product?->has_variants)
                    <div>
                        <label class="mb-1 block text-sm font-medium">How many you have</label>
                        <input type="number" wire:model="stock"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        @error('stock') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                @endunless

                <div>
                    <label class="mb-1 block text-sm font-medium">Tell me when it drops to</label>
                    <input type="number" min="0" wire:model="low_stock_threshold" placeholder="No warning"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('low_stock_threshold') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col justify-center gap-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="track_inventory" class="rounded border-slate-300">
                        Count stock for this product
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="allow_backorder" class="rounded border-slate-300">
                        Keep selling when it runs out
                    </label>
                </div>
            </div>

            @if (is_numeric($price) && is_numeric($cost_price) && (float) $price > 0)
                @php($profit = (float) $price - (float) $cost_price)
                @php($margin = round($profit / (float) $price * 100, 1))
                <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm {{ $profit < 0 ? 'text-rose-700' : 'text-slate-700' }}">
                    You make <strong>{{ $currency }} {{ number_format($profit, 2) }}</strong> on each one,
                    which is {{ $margin }}% of the price.
                    @if ($profit < 0) You are selling this below what it cost you. @endif
                </p>
            @endif
        </div>


    @if ($product)
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Photos</h2>
            <p class="mt-1 mb-4 text-sm text-slate-500">
                The first photo is the one customers see in the shop listing. Big pictures are shrunk for you.
                @if ($product->has_variants)
                    A photo can belong to one combination — the red one — or to the whole product.
                @endif
            </p>

            <div class="flex flex-wrap items-end gap-3">
                @if ($product->has_variants)
                    <div>
                        <label class="mb-1 block text-sm font-medium">This photo is of</label>
                        <select wire:model="photoForVariant"
                                class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                            <option value="">The whole product</option>
                            @foreach ($product->variants as $variant)
                                <option value="{{ $variant->id }}">{{ $variant->choiceLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium">Add photos</label>
                    <input type="file" wire:model="newPhotos" multiple accept="image/*"
                           class="block w-full text-sm file:me-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:text-white hover:file:bg-slate-800">
                    <div wire:loading wire:target="newPhotos" class="mt-1 text-xs text-slate-500">Adding…</div>
                    @error('newPhotos') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    @error('newPhotos.*') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($images->isNotEmpty())
                <div class="mt-6 grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($images as $image)
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="aspect-square bg-slate-100">
                                <img src="{{ $image->thumbnailUrl() }}" alt="{{ $image->alt_text ?: $product->name }}"
                                     class="h-full w-full object-cover">
                            </div>
                            <div class="space-y-2 p-3">
                                @if ($image->is_primary)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Main photo</span>
                                @else
                                    <button type="button" wire:click="makePhotoPrimary({{ $image->id }})"
                                            class="text-xs text-slate-500 hover:text-slate-900">Make this the main photo</button>
                                @endif

                                @if ($product->has_variants)
                                    <select wire:change="assignPhoto({{ $image->id }}, $event.target.value)"
                                            class="w-full rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-slate-500 focus:outline-none">
                                        <option value="" @selected($image->product_variant_id === null)>The whole product</option>
                                        @foreach ($product->variants as $variant)
                                            <option value="{{ $variant->id }}" @selected($image->product_variant_id === $variant->id)>
                                                {{ $variant->choiceLabel() }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif

                                <button type="button" wire:click="deletePhoto({{ $image->id }})"
                                        wire:confirm="Remove this photo?"
                                        class="text-xs text-rose-700 hover:underline">Remove</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-500">
            Save the product and its photos can be added here.
        </div>
    @endif

        <div class="rounded-xl bg-white p-6 shadow-sm">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Choices</h2>
                <button type="button" wire:click="addOption"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Add a choice</button>
            </div>
            <p class="mb-4 text-sm text-slate-500">
                Sizes, colours and so on. Separate the options with commas — for example
                <span class="rounded bg-slate-100 px-1">Small, Medium, Large</span>.
                Every combination becomes something you can price and count on its own.
            </p>

            @forelse ($options as $index => $option)
                <div class="mb-3 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Kind of choice</label>
                        <input type="text" wire:model="options.{{ $index }}.name" placeholder="Size"
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-slate-500">The options</label>
                        <input type="text" wire:model="options.{{ $index }}.values" placeholder="Small, Medium, Large"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <button type="button" wire:click="removeOption({{ $index }})"
                            class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50">Remove</button>
                </div>
            @empty
                <p class="text-sm text-slate-500">This product is sold as one thing, with no choices.</p>
            @endforelse
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-800">
                <span wire:loading.remove wire:target="save">Save product</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            <a href="{{ route('admin.products.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">Cancel</a>
        </div>
    </form>

    @if ($product?->has_variants && count($variantRows) > 0)
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">Each combination</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="py-2 text-start font-semibold">Combination</th>
                            <th class="py-2 text-start font-semibold">Price</th>
                            <th class="py-2 text-start font-semibold">Costs you</th>
                            <th class="py-2 text-start font-semibold">You make</th>
                            <th class="py-2 text-start font-semibold">Code</th>
                            <th class="py-2 text-start font-semibold">How many</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($product->variants as $variant)
                            <tr>
                                <td class="py-2 pe-4 font-medium">{{ $variant->choiceLabel() }}</td>
                                <td class="py-2 pe-4">
                                    <input type="text" inputmode="decimal" wire:model="variantRows.{{ $variant->id }}.price"
                                           class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                    @error('variantRows.'.$variant->id.'.price') <p class="text-xs text-rose-700">{{ $message }}</p> @enderror
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="text" inputmode="decimal" wire:model.blur="variantRows.{{ $variant->id }}.cost"
                                           class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                </td>
                                <td class="py-2 pe-4 tabular-nums text-slate-600">
                                    @php($row = $variantRows[$variant->id] ?? null)
                                    @if ($row && is_numeric($row['price']) && is_numeric($row['cost']))
                                        {{ number_format((float) $row['price'] - (float) $row['cost'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="text" wire:model="variantRows.{{ $variant->id }}.sku"
                                           class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                </td>
                                <td class="py-2">
                                    <input type="number" wire:model="variantRows.{{ $variant->id }}.stock"
                                           class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" wire:click="saveVariants"
                    class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Save the combinations
            </button>
        </div>
    @endif
</div>
