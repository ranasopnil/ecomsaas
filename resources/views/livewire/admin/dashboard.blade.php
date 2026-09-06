@php
    // The activity line, drawn as a smooth path from real figures.
    $points = $chart['points'];
    $peak = $chart['peak'];
    $step = count($points) > 1 ? 100 / (count($points) - 1) : 100;

    $coords = [];
    foreach ($points as $i => $point) {
        $coords[] = [round($i * $step, 2), round(52 - ($point['value'] / $peak) * 44, 2)];
    }

    $line = '';
    foreach ($coords as $i => [$x, $y]) {
        if ($i === 0) {
            $line .= "M {$x} {$y}";
            continue;
        }
        [$px, $py] = $coords[$i - 1];
        $cx = round($px + ($x - $px) / 2, 2);
        $line .= " C {$cx} {$py}, {$cx} {$y}, {$x} {$y}";
    }
    $area = $line.' L 100 56 L 0 56 Z';
@endphp

<div class="space-y-6" wire:poll.60s>
    {{-- Page heading --}}
    <div class="rise flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Shop overview</h1>
            <p class="mt-1 text-sm text-slate-500">Where your shop stands today.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.stock.index') }}" wire:navigate class="btn btn-quiet">Check stock</a>
            <a href="{{ route('admin.products.create') }}" wire:navigate class="btn btn-primary">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <path d="M12 5v14M5 12h14" />
                </svg>
                Add product
            </a>
        </div>
    </div>

    @include('livewire.admin.partials.setup-flow')

    {{-- The four figures. Each one rolls up to its new value on its own. --}}
    <div class="rise rise-2 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $exp = $store->currency_exponent;
            $tiles = [
                ['label' => 'Stock at cost', 'value' => $money['cost']->minor / (10 ** $exp), 'decimals' => $exp,
                 'prefix' => $store->currency.' ', 'text' => $store->currency.' '.$money['cost']->toDisplay(),
                 'note' => 'What you paid for what is on the shelf', 'tone' => 'slate'],
                ['label' => 'Stock at selling price', 'value' => $money['retail']->minor / (10 ** $exp), 'decimals' => $exp,
                 'prefix' => $store->currency.' ', 'text' => $store->currency.' '.$money['retail']->toDisplay(),
                 'note' => 'What it would take if it all sold', 'tone' => 'slate'],
                ['label' => 'Profit in your stock', 'value' => $money['profit']->minor / (10 ** $exp), 'decimals' => $exp,
                 'prefix' => $store->currency.' ', 'text' => $store->currency.' '.$money['profit']->toDisplay(),
                 'note' => $money['margin'] !== null ? $money['margin'].'% of the selling price' : 'Enter costs to see this',
                 'tone' => $money['profit']->minor < 0 ? 'rose' : 'emerald'],
                ['label' => 'Things in stock', 'value' => $stockUnits, 'decimals' => 0, 'prefix' => '',
                 'text' => number_format($stockUnits),
                 'note' => $productCount.' products, '.$onSaleCount.' on sale', 'tone' => 'slate'],
            ];
        @endphp

        @foreach ($tiles as $tile)
            <div class="card card-hover p-5">
                <p class="text-sm text-slate-500">{{ $tile['label'] }}</p>
                <p x-data="counter({{ $tile['value'] }}, {{ $tile['decimals'] }}, @js($tile['prefix']))"
                   x-effect="target = {{ $tile['value'] }}"
                   x-text="display"
                   class="mt-1 inline-block px-1 text-2xl font-bold tabular-nums
                          {{ ['rose' => 'text-rose-600', 'emerald' => 'text-emerald-600'][$tile['tone']] ?? '' }}">{{ $tile['text'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $tile['note'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Activity --}}
        <div class="card rise rise-3 xl:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                <div>
                    <h2 class="font-semibold">Activity</h2>
                    <p class="text-sm text-slate-500">The last fortnight</p>
                </div>

                <div class="flex rounded-xl bg-slate-100 p-1 text-sm">
                    @foreach (['stock' => 'Stock moved', 'products' => 'Products added'] as $key => $label)
                        <button type="button" wire:click="setSeries('{{ $key }}')"
                                class="rounded-lg px-3 py-1.5 transition
                                       {{ $series === $key ? 'bg-white font-medium shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="p-5">
                @if ($chart['total'] === 0)
                    <div class="flex h-48 flex-col items-center justify-center text-center">
                        <p class="text-sm font-medium text-slate-600">Nothing has happened yet</p>
                        <p class="mt-1 max-w-xs text-sm text-slate-500">
                            Once you add products and count stock, the last fortnight shows here.
                        </p>
                    </div>
                @else
                    <svg viewBox="0 0 100 56" preserveAspectRatio="none" class="h-48 w-full">
                        <defs>
                            <linearGradient id="fade" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#6d3ff5" stop-opacity=".28" />
                                <stop offset="100%" stop-color="#6d3ff5" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        @foreach ([8, 20, 32, 44] as $gridline)
                            <line x1="0" x2="100" y1="{{ $gridline }}" y2="{{ $gridline }}" stroke="#f1f5f9" stroke-width=".4" />
                        @endforeach
                        <path d="{{ $area }}" fill="url(#fade)" />
                        <path d="{{ $line }}" fill="none" stroke="#5b3df5" stroke-width="1.1"
                              stroke-linecap="round" stroke-linejoin="round"
                              pathLength="1" style="stroke-dasharray:1;stroke-dashoffset:0;animation:draw .9s ease-out" />
                    </svg>

                    <div class="mt-2 flex justify-between text-xs text-slate-400">
                        <span>{{ $points[0]['label'] }}</span>
                        <span>{{ $points[intdiv(count($points), 2)]['label'] }}</span>
                        <span>{{ end($points)['label'] }}</span>
                    </div>

                    <p class="mt-4 text-sm text-slate-600">
                        <span class="font-semibold">{{ number_format($chart['total']) }}</span>
                        {{ $series === 'products' ? 'products added' : 'items moved in or out' }} in the last fortnight.
                    </p>
                @endif
            </div>
        </div>

        {{-- Stock warnings --}}
        <div class="card rise rise-3">
            <div class="flex items-center justify-between border-b border-slate-100 p-5">
                <h2 class="font-semibold">Needs attention</h2>
                <a href="{{ route('admin.stock.index') }}" wire:navigate class="text-sm text-violet-700 hover:underline">Stock &rarr;</a>
            </div>

            <div class="p-5">
                <div class="mb-4 flex gap-3">
                    <div class="flex-1 rounded-xl bg-rose-50 p-3">
                        <p class="text-2xl font-bold text-rose-700 tabular-nums">{{ number_format($outOfStock) }}</p>
                        <p class="text-xs text-rose-900/70">Sold out</p>
                    </div>
                    <div class="flex-1 rounded-xl bg-amber-50 p-3">
                        <p class="text-2xl font-bold text-amber-700 tabular-nums">{{ number_format($lowStock) }}</p>
                        <p class="text-xs text-amber-900/70">Running low</p>
                    </div>
                </div>

                @forelse ($alerts as $level)
                    <div class="flex items-center justify-between border-b border-slate-50 py-2 text-sm last:border-0">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $level->variant?->product?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $level->variant?->choiceLabel() }}</p>
                        </div>
                        <span class="ms-3 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium
                                     {{ $level->available <= 0 ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $level->available <= 0 ? 'Sold out' : $level->available.' left' }}
                        </span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Nothing needs your attention.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Most valuable stock --}}
        <div class="card rise rise-4 xl:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 p-5">
                <h2 class="font-semibold">Most money on the shelf</h2>
                <a href="{{ route('admin.products.index') }}" wire:navigate class="text-sm text-violet-700 hover:underline">Products &rarr;</a>
            </div>

            <div class="divide-y divide-slate-50">
                @forelse ($topProducts as $row)
                    @php($variant = $row['variant'])
                    @php($image = $variant->displayImage())
                    <div class="flex items-center gap-4 p-4">
                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                            @if ($image)
                                <img src="{{ $image->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $variant->product?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $variant->choiceLabel() }}</p>
                        </div>
                        <div class="text-end">
                            <p class="text-sm font-semibold tabular-nums">{{ $store->currency }} {{ $row['value']->toDisplay() }}</p>
                            <p class="text-xs text-slate-500">{{ number_format($variant->inventory?->available ?? 0) }} in stock</p>
                        </div>
                    </div>
                @empty
                    <p class="p-8 text-center text-sm text-slate-500">Add a product and its stock to see this.</p>
                @endforelse
            </div>
        </div>

        {{-- What happened lately --}}
        <div class="card rise rise-4">
            <div class="border-b border-slate-100 p-5">
                <h2 class="font-semibold">Latest changes</h2>
                <p class="text-sm text-slate-500">Every stock change, as it happened</p>
            </div>

            <div class="divide-y divide-slate-50">
                @forelse ($activity as $movement)
                    <div class="flex items-start gap-3 p-4">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold
                                     {{ $movement->quantity_change >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $movement->quantity_change >= 0 ? '+' : '−' }}{{ abs($movement->quantity_change) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $movement->variant?->product?->name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $movement->note ?: str_replace('_', ' ', $movement->reason) }} ·
                                {{ $movement->created_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="p-8 text-center text-sm text-slate-500">No stock changes yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($money['profit']->minor < 0)
        <div class="rise rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            At these prices you would lose money on the stock you hold. Check your prices and your costs.
        </div>
    @endif

    @if ($money['missingCost'] > 0)
        <div class="rise rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ number_format($money['missingCost']) }} thing(s) you sell have no cost entered, so they are left out of the
            money figures. <a href="{{ route('admin.products.index') }}" wire:navigate class="underline">Add costs</a>.
        </div>
    @endif

    <div class="text-sm text-slate-500">
        @if ($productAllowance === null)
            Your plan lets you add as many products as you like.
        @else
            Your plan allows {{ number_format($productAllowance) }} products — {{ number_format($productsLeft) }} left.
        @endif
    </div>
</div>
