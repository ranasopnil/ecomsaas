@php
    use App\Models\LedgerEntry;

    $shop = App\Facades\Tenancy::current();
    $symbol = config('currencies.'.$shop->currency.'.symbol', $shop->currency.' ');

    $periods = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'all' => 'Everything',
    ];
@endphp

<div class="space-y-6">
    <div class="rise flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Accounts</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                Your book of money in and money out. Payments and cash from couriers are written here on their own;
                anything else you write yourself. Nothing here is ever edited — a mistake is put right by writing
                its opposite beside it.
            </p>
        </div>
        @unless ($adding || $reversing)
            <button type="button" wire:click="add" class="btn btn-primary">Write something down</button>
        @endunless
    </div>

    {{-- Where the shop stands --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card rise rise-1 p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Money in</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">
                {{ $symbol }}{{ $totals['in']->toDisplay() }}
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ $periods[$period] ?? 'This month' }}</p>
        </div>

        <div class="card rise rise-1 p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Money out</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-rose-700">
                {{ $symbol }}{{ $totals['out']->toDisplay() }}
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ $periods[$period] ?? 'This month' }}</p>
        </div>

        <div class="card rise rise-2 p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Left over</p>
            <p class="mt-1 text-2xl font-bold tabular-nums {{ $totals['net']->minor < 0 ? 'text-rose-700' : '' }}">
                {{ $symbol }}{{ $totals['net']->toDisplay() }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                All time: {{ $symbol }}{{ $balance->toDisplay() }}
            </p>
        </div>

        <div class="card rise rise-2 p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Couriers are holding</p>
            <p class="mt-1 text-2xl font-bold tabular-nums {{ $owed->minor > 0 ? 'text-amber-700' : '' }}">
                {{ $symbol }}{{ $owed->toDisplay() }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                <a href="{{ route('admin.orders.index') }}?show=cash" wire:navigate class="hover:underline">
                    Cash on delivered orders, not yet handed over
                </a>
            </p>
        </div>
    </div>

    {{-- Writing one down --}}
    @if ($adding)
        <form wire:submit="save" class="card rise p-5">
            <h2 class="mb-4 font-semibold">Write something down</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Which way did the money go?</label>
                    <select wire:model="direction"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-rose-400 focus:outline-none">
                        <option value="out">Money out — something you spent</option>
                        <option value="in">Money in — something you took</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">How much</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-500">{{ $symbol }}</span>
                        <input type="text" inputmode="decimal" wire:model="amount" placeholder="500"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-rose-400 focus:outline-none">
                    </div>
                    @error('amount') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">What was it for?</label>
                    <input type="text" wire:model="description" maxlength="200"
                           placeholder="Packing boxes, shop rent, courier charges, money from a walk-in customer…"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-rose-400 focus:outline-none">
                    @error('description') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">The day it happened</label>
                    <input type="date" wire:model="occurred_on"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-rose-400 focus:outline-none">
                    @error('occurred_on') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-5 flex items-center gap-3">
                <button type="submit" class="btn btn-primary">Write it down</button>
                <button type="button" wire:click="cancel" class="text-sm text-slate-500 hover:text-slate-900">Cancel</button>
            </div>
        </form>
    @endif

    {{-- Putting one right --}}
    @if ($reversing)
        <form wire:submit="reverse" class="card rise p-5">
            <h2 class="font-semibold">Put this line right</h2>
            <p class="mt-1 text-sm text-slate-500">
                The line stays exactly as it is. Its opposite is written beside it, so the book shows both what was
                entered and what put it right.
            </p>

            <label class="mb-1 mt-4 block text-sm font-medium">Why?</label>
            <input type="text" wire:model="why" maxlength="200" placeholder="Entered twice by mistake"
                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-rose-400 focus:outline-none">

            <div class="mt-5 flex items-center gap-3">
                <button type="submit" class="btn btn-primary">Write the opposite line</button>
                <button type="button" wire:click="cancel" class="text-sm text-slate-500 hover:text-slate-900">Cancel</button>
            </div>
        </form>
    @endif

    {{-- The book --}}
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
            <div class="flex gap-1">
                @foreach (['all' => 'Everything', 'in' => 'Money in', 'out' => 'Money out'] as $key => $label)
                    <button type="button" wire:click="$set('only', '{{ $key }}')"
                            class="rounded-xl px-3 py-1.5 text-sm transition
                                   {{ $only === $key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <select wire:model.live="period" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                @foreach ($periods as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($entries->isEmpty())
            <div class="p-10 text-center">
                <h2 class="text-base font-semibold">Nothing in the book for this stretch</h2>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                    Payments and cash from couriers appear here on their own as they come in. Anything else —
                    rent, packing, a walk-in sale — you write down yourself.
                </p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Day</th>
                        <th class="px-4 py-3">What it was</th>
                        <th class="px-4 py-3 text-end">In</th>
                        <th class="px-4 py-3 text-end">Out</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}"
                            class="{{ in_array($entry->id, $reversed, true) ? 'opacity-50' : '' }}">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ $entry->occurred_on->format('j M Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $entry->description }}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5">{{ $entry->kindLabel() }}</span>
                                    @if ($entry->order)
                                        <a href="{{ route('admin.orders.show', $entry->order) }}" wire:navigate
                                           class="text-rose-700 hover:underline">{{ $entry->order->reference }}</a>
                                    @endif
                                    @if ($entry->user_name)
                                        <span>by {{ $entry->user_name }}</span>
                                    @endif
                                    @if (in_array($entry->id, $reversed, true))
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-rose-800">Put right</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-end font-semibold tabular-nums text-emerald-700">
                                {{ $entry->isMoneyIn() ? $symbol.$entry->amount->toDisplay() : '' }}
                            </td>
                            <td class="px-4 py-3 text-end font-semibold tabular-nums text-rose-700">
                                {{ $entry->isMoneyIn() ? '' : $symbol.$entry->amount->toDisplay() }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                @if ($entry->reverses_id === null
                                    && $entry->kind !== LedgerEntry::KIND_CORRECTION
                                    && ! in_array($entry->id, $reversed, true))
                                    <button type="button" wire:click="startReversing({{ $entry->id }})"
                                            class="text-xs text-slate-500 hover:text-slate-900 hover:underline">
                                        Put right
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{ $entries->links() }}
</div>
