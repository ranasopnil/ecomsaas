<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('admin.products.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">&larr; Products</a>
            <h1 class="mt-1 text-3xl font-bold tracking-tight">{{ $product ? 'Edit '.$product->name : 'New product' }}</h1>
        </div>

        @if ($product)
            <a href="{{ route('storefront.product', $product->slug) }}" target="_blank" rel="noopener"
               class="btn btn-quiet">
                View as a customer &nearr;
            </a>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="card p-6">
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

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Short description</label>
                    <textarea wire:model.blur="short_description" rows="2" maxlength="500"
                              placeholder="One or two sentences. Shown under the name and used by Google."
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"></textarea>
                    <p class="mt-1 text-xs text-slate-500">
                        This is what people read in search results. Keep it under about 160 characters.
                    </p>
                    @error('short_description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Search words</label>
                    <input type="text" wire:model.blur="tags" placeholder="panjabi, cotton, eid, menswear"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">
                        Words a customer might type when looking for this. Separate them with commas.
                    </p>
                    @error('tags') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">YouTube video (optional)</label>
                    <input type="url" wire:model.blur="video_url" placeholder="https://www.youtube.com/watch?v=..."
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">Shown on the product page under the photos.</p>
                    @error('video_url') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
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
                    <label class="mb-1 block text-sm font-medium">Publish this product</label>
                    <div class="flex items-center gap-4 rounded-lg border border-slate-300 px-3 py-2">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model="is_published" value="1" class="border-slate-300">
                            Yes — customers can see it
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model="is_published" value="0" class="border-slate-300">
                            No — only you
                        </label>
                    </div>
                    @if ($status === 'archived')
                        <p class="mt-1 text-xs text-amber-700">
                            This product is put away. Publishing it here will not bring it back —
                            use "Put back" on the products list.
                        </p>
                    @endif
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

        <div class="card p-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">
                Price and stock @if ($product?->has_variants) <span class="normal-case text-slate-400">(the starting point for each choice)</span> @endif
            </h2>

            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium">What the price is for</label>
                <input type="text" wire:model.blur="unit" list="unit-suggestions" maxlength="40"
                       placeholder="per kg"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none sm:max-w-xs">
                <datalist id="unit-suggestions">
                    @foreach (['per kg', 'per 500 g', 'per piece', 'per dozen', 'per bunch', 'per litre', 'per pack', 'per bottle', 'per box'] as $suggestion)
                        <option value="{{ $suggestion }}"></option>
                    @endforeach
                </datalist>
                <p class="mt-1 text-xs text-slate-500">
                    Shown next to the price everywhere, so a customer knows what they are paying for.
                    Leave it empty if the price is simply for one of the thing.
                </p>
                @error('unit') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">Regular price</label>
                    <div class="flex items-center rounded-lg border border-slate-300 focus-within:border-slate-500">
                        <span class="px-3 text-sm text-slate-500">{{ $currency }}</span>
                        <input type="text" inputmode="decimal" wire:model.blur="regular_price"
                               class="w-full rounded-e-lg border-0 px-2 py-2 text-sm focus:outline-none">
                    </div>
                    @error('regular_price') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Discount price (optional)</label>
                    <div class="flex items-center rounded-lg border border-slate-300 focus-within:border-slate-500">
                        <span class="px-3 text-sm text-slate-500">{{ $currency }}</span>
                        <input type="text" inputmode="decimal" wire:model.blur="discount_price"
                               class="w-full rounded-e-lg border-0 px-2 py-2 text-sm focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Fill this in for a sale. Customers pay this, with the regular price crossed out beside it.
                    </p>
                    @error('discount_price') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
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

            @php($selling = is_numeric($discount_price) && (float) $discount_price > 0 ? $discount_price : $regular_price)
            @if (is_numeric($selling) && is_numeric($cost_price) && (float) $selling > 0)
                @php($profit = (float) $selling - (float) $cost_price)
                @php($margin = round($profit / (float) $selling * 100, 1))
                <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm {{ $profit < 0 ? 'text-rose-700' : 'text-slate-700' }}">
                    You make <strong>{{ $currency }} {{ number_format($profit, 2) }}</strong> on each one,
                    which is {{ $margin }}% of the price.
                    @if ($profit < 0) You are selling this below what it cost you. @endif
                </p>
            @endif
        </div>



        <div class="card p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Delivery</h2>
            <p class="mt-1 mb-4 text-sm text-slate-500">
                Size and weight are optional, but couriers price on them, so filling them in means the
                delivery charge can be worked out properly later.
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium">Delivery charge</label>
                    <div class="flex items-center rounded-lg border border-slate-300 focus-within:border-slate-500">
                        <span class="px-3 text-sm text-slate-500">{{ $currency }}</span>
                        <input type="text" inputmode="decimal" wire:model.blur="shipping_charge" placeholder="Shop's usual"
                               class="w-full rounded-e-lg border-0 px-2 py-2 text-sm focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Leave empty for the shop's usual charge. Put 0 for free delivery.</p>
                    @error('shipping_charge') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Weight (grams)</label>
                    <input type="number" min="0" wire:model.blur="weight_grams" placeholder="450"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    @error('weight_grams') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Size (millimetres)</label>
                    <div class="flex items-center gap-2">
                        <input type="number" min="0" wire:model.blur="length_mm" placeholder="Length"
                               class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <span class="text-slate-400">×</span>
                        <input type="number" min="0" wire:model.blur="width_mm" placeholder="Width"
                               class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-slate-500 focus:outline-none">
                        <span class="text-slate-400">×</span>
                        <input type="number" min="0" wire:model.blur="height_mm" placeholder="Height"
                               class="w-full rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    @error('length_mm') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    @error('width_mm') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    @error('height_mm') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

    @if ($product)
        <div class="card p-6">
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
                        <div wire:key="photo-{{ $image->id }}"
                             class="overflow-hidden rounded-xl border border-slate-200 {{ $justTouchedPhoto === $image->id ? 'settled' : '' }}">
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

        <div class="card p-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-400">Where you deliver it</h2>
            <p class="mb-4 text-sm text-slate-500">
                Customers outside the area do not see this product at all.
            </p>

            <div class="space-y-2">
                @php($choices = [
                    \App\Models\Product::AVAILABLE_SHOP => [
                        'Wherever my shop delivers',
                        $shopDeliversEverywhere
                            ? 'You have not named any delivery areas, so this reaches every customer.'
                            : 'All of your areas: '.$deliveryAreas->pluck('name')->join(', ', ' and ').'.',
                    ],
                    \App\Models\Product::AVAILABLE_ANYWHERE => [
                        'Anywhere',
                        'Every customer sees it, even outside your areas. Good for anything you post.',
                    ],
                    \App\Models\Product::AVAILABLE_AREAS => [
                        'Only certain areas',
                        $shopDeliversEverywhere
                            ? 'Name your areas on the Delivery areas screen first, then pick them here.'
                            : 'Pick the areas this one goes to. Good for anything you cannot send far.',
                    ],
                ])

                @foreach ($choices as $value => [$label, $hint])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition
                                  {{ $availability === $value ? 'border-violet-400 bg-violet-50/50' : 'border-slate-200 hover:border-slate-300' }}">
                        <input type="radio" wire:model.live="availability" value="{{ $value }}" class="mt-1">
                        <span>
                            <span class="block text-sm font-medium">{{ $label }}</span>
                            <span class="block text-xs text-slate-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @if ($availability === \App\Models\Product::AVAILABLE_AREAS)
                <div class="mt-5 border-t border-slate-100 pt-5">
                    @if ($deliveryAreas->isEmpty())
                        <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            You have not named any delivery areas yet.
                            <a href="{{ route('admin.delivery.edit') }}" wire:navigate class="underline">Add one first</a>,
                            then come back and pick it here. Until then this product goes wherever your shop goes.
                        </div>
                    @else
                        <h3 class="mb-3 text-sm font-semibold">Which areas does this one go to?</h3>

                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($deliveryAreas as $area)
                                <label wire:key="pa-{{ $area->id }}"
                                       class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition
                                              {{ in_array($area->id, $delivery_area_ids) ? 'border-emerald-400 bg-emerald-50/50' : 'border-slate-200 hover:border-slate-300' }}">
                                    <input type="checkbox" wire:model.live="delivery_area_ids" value="{{ $area->id }}"
                                           class="mt-1 rounded border-slate-300">
                                    <span>
                                        <span class="block text-sm font-medium">{{ $area->name }}</span>
                                        <span class="block text-xs text-slate-500">Within {{ $area->distance() }} km</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @if ($delivery_area_ids === [])
                            <p class="mt-3 text-xs text-slate-500">
                                Nothing picked, so this product goes wherever your shop goes.
                            </p>
                        @endif

                        <p class="mt-3 text-xs text-slate-500">
                            Areas are named on the
                            <a href="{{ route('admin.delivery.edit') }}" wire:navigate class="underline">Delivery areas</a> screen.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="card p-6">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Variants</h2>
                <button type="button" wire:click="addOption"
                        class="btn btn-primary !px-3 !py-1.5">
                    + Add variant
                </button>
            </div>
            <p class="mb-4 text-sm text-slate-500">
                Give the variant a name, such as <span class="rounded bg-slate-100 px-1">Size</span>, then list what it
                can be, separated by commas: <span class="rounded bg-slate-100 px-1">Small, Medium, Large</span>.
                Every combination becomes something you can price, count and photograph on its own.
            </p>

            @forelse ($options as $index => $option)
                <div class="mb-3 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Variant name</label>
                        <input type="text" wire:model="options.{{ $index }}.name" placeholder="Size"
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-slate-500">What it can be</label>
                        <input type="text" wire:model="options.{{ $index }}.values" placeholder="Small, Medium, Large"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                    </div>
                    <button type="button" wire:click="removeOption({{ $index }})"
                            class="rounded-lg border border-rose-200 px-3 py-2 text-sm text-rose-700 hover:bg-rose-50">Remove</button>
                </div>
            @empty
                <p class="text-sm text-slate-500">This product is sold as one thing, with no variants.</p>
            @endforelse
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn btn-primary !px-5">
                <span wire:loading.remove wire:target="save">Save product</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            <a href="{{ route('admin.products.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">Cancel</a>
        </div>
    </form>

    @if ($product?->has_variants && count($variantRows) > 0)
        <div class="card p-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">Each variant combination</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="py-2 text-start font-semibold">Combination</th>
                            <th class="py-2 text-start font-semibold">Regular price</th>
                            <th class="py-2 text-start font-semibold">Discount price</th>
                            <th class="py-2 text-start font-semibold">Costs you</th>
                            <th class="py-2 text-start font-semibold">You make</th>
                            <th class="py-2 text-start font-semibold">Weight (g)</th>
                            <th class="py-2 text-start font-semibold">Code</th>
                            <th class="py-2 text-start font-semibold">How many</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($product->variants as $variant)
                            <tr>
                                <td class="py-2 pe-4 font-medium">{{ $variant->choiceLabel() }}</td>
                                <td class="py-2 pe-4">
                                    <input type="text" inputmode="decimal" wire:model="variantRows.{{ $variant->id }}.regular"
                                           class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                    @error('variantRows.'.$variant->id.'.regular') <p class="text-xs text-rose-700">{{ $message }}</p> @enderror
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="text" inputmode="decimal" wire:model="variantRows.{{ $variant->id }}.discount"
                                           class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                    @error('variantRows.'.$variant->id.'.discount') <p class="text-xs text-rose-700">{{ $message }}</p> @enderror
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="text" inputmode="decimal" wire:model.blur="variantRows.{{ $variant->id }}.cost"
                                           class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
                                </td>
                                <td class="py-2 pe-4 tabular-nums text-slate-600">
                                    @php($row = $variantRows[$variant->id] ?? null)
                                    @php($sells = $row && is_numeric($row['discount'] ?? '') && (float) $row['discount'] > 0 ? $row['discount'] : ($row['regular'] ?? null))
                                    @if ($row && is_numeric($sells) && is_numeric($row['cost']))
                                        {{ number_format((float) $sells - (float) $row['cost'], 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2 pe-4">
                                    <input type="number" min="0" wire:model="variantRows.{{ $variant->id }}.weight"
                                           class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-slate-500 focus:outline-none">
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
                    class="btn btn-primary mt-4">
                Save the combinations
            </button>
        </div>
    @endif
</div>
