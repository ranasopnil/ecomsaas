<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-semibold">Overview</h1>
        <p class="mt-1 text-sm text-slate-500">How the platform stands today.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Shops', $storeCount],
            ['Shops open for business', $activeStoreCount],
            ['Plans', $packageCount],
        ] as [$label, $value])
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-4 text-sm font-semibold">Shops on each plan</h2>

        @if ($planTotals->isEmpty())
            <p class="px-5 py-6 text-sm text-slate-500">No shop is on a plan yet.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($planTotals as $total)
                    <li class="flex items-center justify-between px-5 py-3 text-sm">
                        <span>{{ $total->package?->name ?? 'Removed plan' }}</span>
                        <span class="tabular-nums font-medium">{{ number_format($total->shops) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
