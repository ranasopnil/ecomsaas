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
