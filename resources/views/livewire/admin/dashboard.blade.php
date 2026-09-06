<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-semibold">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">How your shop stands today.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        @foreach ([
            ['Products', $productCount],
            ['On sale', $onSaleCount],
            ['Out of stock', $outOfStock],
            ['Running low', $lowStock],
        ] as [$label, $value])
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold">Money in your stock</h2>
        <p class="mt-1 text-sm text-slate-500">
            Worked out from what you paid and what you charge, for the stock you have on the shelf right now.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <p class="text-sm text-slate-500">What it cost you</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $store->currency }} {{ $money['cost']->toDecimal() }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">What it would sell for</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $store->currency }} {{ $money['retail']->toDecimal() }}</p>
            </div>
            <div>
                <p class="text-sm text-slate-500">You would make</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $money['profit']->minor < 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                    {{ $store->currency }} {{ $money['profit']->toDecimal() }}
                </p>
                @if ($money['margin'] !== null)
                    <p class="text-xs text-slate-500">{{ $money['margin'] }}% of the selling price</p>
                @endif
            </div>
        </div>

        @if ($money['missingCost'] > 0)
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ number_format($money['missingCost']) }} thing(s) you sell have no cost entered yet, so they are left
                out of these figures. Put the cost on the product page to include them.
            </p>
        @endif

        @if ($money['profit']->minor < 0)
            <p class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900">
                At these prices you would lose money on the stock you hold. Check your prices and your costs.
            </p>
        @endif
    </div>

    <div class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold">Your plan</h2>
        <p class="mt-2 text-sm text-slate-600">
            @if ($productAllowance === null)
                Your plan lets you add as many products as you like.
            @else
                Your plan allows {{ number_format($productAllowance) }} products.
                You have {{ number_format($productsLeft) }} left.
            @endif
        </p>
    </div>

    <div class="flex gap-3">
        <a href="{{ route('admin.products.create') }}" wire:navigate
           class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Add a product</a>
        <a href="{{ route('admin.stock.index') }}" wire:navigate
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-50">Check stock</a>
    </div>
</div>
