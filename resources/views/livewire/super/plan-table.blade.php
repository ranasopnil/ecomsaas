<div class="space-y-5">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Plans table</h1>
            <p class="mt-1 text-sm text-slate-500">
                What every shop owner reads on their plan page. Sections, rows, and what each plan says in each row.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($groups->isEmpty())
                <button type="button" wire:click="loadStandard" class="btn btn-quiet">Lay out the standard table</button>
            @endif
            <button type="button" wire:click="save" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Save the table</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>

    @if ($message !== '')
        <div class="rise rounded-xl border px-4 py-3 text-sm
                    {{ $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900' }}">
            {{ $message }}
        </div>
    @endif

    @if ($packages->isEmpty())
        <div class="card p-10 text-center">
            <p class="text-sm font-medium">No plan is on sale yet</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                A comparison table needs something to compare. Make a plan first.
            </p>
            <a href="{{ route('super.packages.create') }}" wire:navigate class="btn btn-primary mt-4">Make a plan</a>
        </div>
    @else

    {{-- ------------------------------------------------------------------
         The words above the table
    ------------------------------------------------------------------- --}}
    <div class="card p-5">
        <h2 class="font-semibold">The words above the table</h2>
        <p class="mt-0.5 text-xs text-slate-500">Yours to write. Nothing here is worked out from anything.</p>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium">Small label</span>
                <input type="text" wire:model="eyebrow" placeholder="Plans &amp; pricing"
                       class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                @error('eyebrow') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium">Heading</span>
                    <input type="text" wire:model="heading" placeholder="Compare plans. Choose what fits"
                           class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                    @error('heading') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-sm font-medium">…and the part in pink</span>
                    <input type="text" wire:model="headingAccent" placeholder="your business."
                           class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                    @error('headingAccent') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="block lg:col-span-2">
                <span class="text-sm font-medium">The line underneath</span>
                <textarea wire:model="blurb" rows="2"
                          class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none"></textarea>
                @error('blurb') <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="mt-5 border-t border-slate-100 pt-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold">The promises across the top</h3>
                    <p class="text-xs text-slate-500">
                        These are promises the platform makes. Only write what you will actually stand behind.
                    </p>
                </div>
                <button type="button" wire:click="addPromise" class="btn btn-quiet !py-1.5 !text-xs">Add one</button>
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($promises as $at => $promise)
                    <div wire:key="promise-{{ $at }}" class="rounded-xl border border-slate-100 p-3">
                        <select wire:model="promises.{{ $at }}.icon"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-blue-400 focus:outline-none">
                            @foreach (['tag' => 'Price tag', 'shield' => 'Shield', 'headset' => 'Headset', 'spark' => 'Spark', 'bag' => 'Bag', 'van' => 'Van'] as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>

                        <input type="text" wire:model="promises.{{ $at }}.title" placeholder="No hidden fees"
                               class="mt-2 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm font-medium focus:border-blue-400 focus:outline-none">

                        <input type="text" wire:model="promises.{{ $at }}.detail" placeholder="Transparent pricing"
                               class="mt-1.5 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-blue-400 focus:outline-none">

                        <button type="button" wire:click="removePromise({{ $at }})"
                                class="mt-2 text-xs text-slate-400 hover:text-rose-600">Remove</button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         The columns
    ------------------------------------------------------------------- --}}
    <div class="card p-5">
        <h2 class="font-semibold">The columns</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            One plan per column, in the order they are sold. The price itself is on the plan —
            <a href="{{ route('super.packages.index') }}" wire:navigate class="font-semibold text-blue-600">edit a plan</a>
            to change what it costs.
        </p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($packages as $package)
                <div wire:key="col-{{ $package->id }}"
                     class="rounded-xl border p-3.5 {{ $popular === $package->id ? 'border-blue-300 bg-blue-50/50' : 'border-slate-100' }}">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="font-semibold">{{ $package->name }}</span>
                        <a href="{{ route('super.packages.edit', $package) }}" wire:navigate
                           class="text-xs font-semibold text-blue-600">Price &amp; limits</a>
                    </div>

                    <label class="mt-2.5 flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="popular" value="{{ $package->id }}" name="popular"
                               class="border-slate-300 text-blue-600">
                        Most popular
                    </label>

                    <label class="mt-2.5 block">
                        <span class="text-xs text-slate-500">Badge over it</span>
                        <input type="text" wire:model="badges.{{ $package->id }}" placeholder="Most popular"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-blue-400 focus:outline-none">
                    </label>

                    <label class="mt-2 block">
                        <span class="text-xs text-slate-500">What its button says</span>
                        <input type="text" wire:model="ctas.{{ $package->id }}" placeholder="Choose plan"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-blue-400 focus:outline-none">
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         The rows
    ------------------------------------------------------------------- --}}
    @foreach ($groups as $group)
        <div wire:key="group-{{ $group->id }}" class="card p-5">
            <div class="flex flex-wrap items-start gap-3">
                <div class="min-w-0 flex-1">
                    <input type="text" wire:model="groupNames.{{ $group->id }}"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold uppercase tracking-wide focus:border-blue-400 focus:outline-none">
                    @error('groupNames.'.$group->id) <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror

                    <input type="text" wire:model="groupNotes.{{ $group->id }}"
                           placeholder="A small note beside the section heading (optional)"
                           class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs focus:border-blue-400 focus:outline-none">
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <button type="button" wire:click="moveGroup({{ $group->id }}, -1)"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs hover:bg-slate-50" title="Move up">↑</button>
                    <button type="button" wire:click="moveGroup({{ $group->id }}, 1)"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs hover:bg-slate-50" title="Move down">↓</button>
                    <button type="button" wire:click="removeGroup({{ $group->id }})"
                            wire:confirm="Take '{{ $group->name }}' and every row in it off the table?"
                            class="rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-rose-600 hover:bg-rose-50">Remove</button>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[44rem] text-sm">
                    <thead>
                        <tr class="text-xs text-slate-400">
                            <th class="w-72 px-2 py-2 text-start font-medium">Row</th>
                            @foreach ($packages as $package)
                                <th class="px-2 py-2 text-center font-medium">{{ $package->name }}</th>
                            @endforeach
                            <th class="w-24 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach ($group->features as $row)
                            @php($enforced = $row->isEnforced())

                            <tr wire:key="row-{{ $row->id }}">
                                <td class="px-2 py-2 align-top">
                                    <input type="text" wire:model="rowNames.{{ $row->id }}"
                                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                                    @error('rowNames.'.$row->id) <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span> @enderror

                                    <input type="text" wire:model="rowNotes.{{ $row->id }}" placeholder="Small grey note (optional)"
                                           class="mt-1.5 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-blue-400 focus:outline-none">

                                    <select wire:model.live="rowFeatures.{{ $row->id }}"
                                            class="mt-1.5 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-blue-400 focus:outline-none">
                                        <option value="">Just words — you type each column</option>
                                        @foreach ($features as $key => $definition)
                                            <option value="{{ $key }}">Read from “{{ $definition['label'] }}”</option>
                                        @endforeach
                                    </select>

                                    <p class="mt-1 text-[0.68rem] leading-snug {{ $enforced ? 'text-emerald-600' : 'text-slate-400' }}">
                                        @if ($enforced)
                                            Read from what each plan really allows. It can never disagree with what the
                                            platform keeps.
                                        @else
                                            Words only. Nothing in the platform enforces this row.
                                        @endif
                                    </p>
                                </td>

                                @foreach ($packages as $package)
                                    <td class="px-2 py-2 text-center align-top">
                                        @if ($enforced)
                                            @php($cell = collect($preview)
                                                ->flatMap(fn ($section) => $section['rows'])
                                                ->firstWhere('row.id', $row->id)['cells'][$package->id] ?? null)

                                            <span class="block rounded-lg bg-slate-50 px-2 py-2 text-xs font-semibold text-slate-500">
                                                {{ $cell === null ? '—' : ($cell['kind'] === 'tick' ? '✓' : ($cell['text'] ?? '—')) }}
                                            </span>
                                        @else
                                            <input type="text" wire:model="cells.{{ $row->id }}.{{ $package->id }}"
                                                   placeholder="yes / no / 500"
                                                   class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-center text-sm focus:border-blue-400 focus:outline-none">
                                        @endif
                                    </td>
                                @endforeach

                                <td class="px-2 py-2 align-top">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" wire:click="moveRow({{ $row->id }}, -1)"
                                                class="rounded-lg border border-slate-200 px-1.5 py-1 text-xs hover:bg-slate-50" title="Move up">↑</button>
                                        <button type="button" wire:click="moveRow({{ $row->id }}, 1)"
                                                class="rounded-lg border border-slate-200 px-1.5 py-1 text-xs hover:bg-slate-50" title="Move down">↓</button>
                                        <button type="button" wire:click="removeRow({{ $row->id }})"
                                                wire:confirm="Take '{{ $row->name }}' off the table?"
                                                class="rounded-lg border border-slate-200 px-1.5 py-1 text-xs text-rose-600 hover:bg-rose-50" title="Remove">✕</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                <input type="text" wire:model="newRow.{{ $group->id }}" placeholder="Add a row to this section"
                       class="w-full max-w-xs rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
                <button type="button" wire:click="addRow({{ $group->id }})" class="btn btn-quiet !py-2 !text-xs">Add row</button>
            </div>
        </div>
    @endforeach

    <div class="card p-5">
        <h2 class="font-semibold">Add a section</h2>
        <p class="mt-0.5 text-xs text-slate-500">A new band across the table, like “Selling” or “Operations”.</p>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <input type="text" wire:model="newGroup" placeholder="Section name"
                   class="w-full max-w-xs rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
            <button type="button" wire:click="addGroup" class="btn btn-quiet !py-2 !text-xs">Add section</button>

            @if ($groups->isNotEmpty())
                <button type="button" wire:click="loadStandard" class="ms-auto text-xs font-semibold text-blue-600">
                    Put back anything missing from the standard table
                </button>
            @endif
        </div>
    </div>

    {{-- ------------------------------------------------------------------
         What a shop owner sees. The same drawing, from the same figures.
    ------------------------------------------------------------------- --}}
    <div>
        <h2 class="mb-2 font-semibold">What a shop owner sees</h2>
        <p class="mb-3 text-xs text-slate-500">
            The last thing you saved, drawn exactly as it appears on their plan page. Save to see changes here.
        </p>

        <div class="shop-admin rounded-3xl p-1">
            <x-plans.comparison
                :settings="$settings"
                :sections="$preview"
                :packages="$packages"
                :currency="$packages->first()?->currency ?? 'BDT'"
                :symbol="config('currencies.'.($packages->first()?->currency ?? 'BDT').'.symbol', '')"
                :interactive="false" />
        </div>
    </div>

    @endif

    <div class="flex justify-end">
        <button type="button" wire:click="save" class="btn btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">Save the table</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>
</div>
