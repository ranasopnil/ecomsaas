@php
    use App\Models\Order;

    $symbol = fn ($order) => config('currencies.'.$order->currency.'.symbol', $order->currency.' ');

    $tone = fn ($order) => match ($order->status) {
        Order::STATUS_DELIVERED => 'bg-emerald-100 text-emerald-800',
        Order::STATUS_CANCELLED => 'bg-rose-100 text-rose-800',
        Order::STATUS_NOT_DELIVERED => 'bg-amber-100 text-amber-900',
        Order::STATUS_PENDING_PAYMENT => 'bg-slate-100 text-slate-500',
        Order::STATUS_PLACED => 'bg-violet-100 text-violet-800',
        default => 'bg-sky-100 text-sky-800',
    };
@endphp

<div class="space-y-6">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold">Orders</h1>
            <p class="text-sm text-slate-500">
                {{ $takenToday }} {{ $takenToday === 1 ? 'order' : 'orders' }} today.
                @if ($counts['open'] > 0)
                    {{ $counts['open'] }} still {{ $counts['open'] === 1 ? 'needs' : 'need' }} something doing.
                @endif
                @if ($owed->minor > 0)
                    Couriers are holding
                    {{ config('currencies.'.$owed->currency.'.symbol', $owed->currency.' ') }}{{ $owed->toDisplay() }}
                    of yours.
                @endif
            </p>
        </div>

        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, number, order or consignment no."
               class="w-72 rounded-xl border border-slate-200 px-3 py-2 text-sm">
    </div>

    {{-- What has to happen to them --}}
    <div class="-mx-1 flex gap-1 overflow-x-auto pb-1">
        @foreach ($tabs as $key => $tab)
            <button type="button" wire:click="showing('{{ $key }}')"
                    class="flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm transition
                           {{ $show === $key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                {{ $tab['label'] }}
                <span class="rounded-full px-1.5 text-xs
                             {{ $show === $key ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">
                    {{ $counts[$key] }}
                </span>
            </button>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        @if ($orders->isEmpty())
            <div class="p-10 text-center">
                <h2 class="text-base font-semibold">No orders here yet</h2>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                    They will appear the moment a customer checks out.
                </p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Going to</th>
                        <th class="px-4 py-3">Where it is</th>
                        <th class="px-4 py-3 text-end">Total</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($orders as $order)
                        <tr class="{{ $order->isReal() ? '' : 'opacity-60' }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                                   class="font-semibold text-slate-900 hover:underline">{{ $order->reference }}</a>
                                <div class="text-xs text-slate-500">
                                    {{ ($order->placed_at ?? $order->created_at)->diffForHumans() }}
                                    · {{ $order->lines_count }} {{ $order->lines_count === 1 ? 'line' : 'lines' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $order->customer_name }}</div>
                                <div class="text-xs text-slate-500">{{ $order->customer_phone }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $order->delivery_area_name ?: '—' }}
                                @if ($order->courier_name)
                                    <div class="text-xs text-slate-500">{{ $order->courier_name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $tone($order) }}">{{ $order->statusLabel() }}</span>
                                <div class="mt-0.5 text-xs text-slate-500">{{ $order->paymentLabel() }}</div>
                            </td>
                            <td class="px-4 py-3 text-end font-semibold tabular-nums">
                                {{ $symbol($order) }}{{ $order->total->toDisplay() }}
                            </td>
                            <td class="px-4 py-3 text-end">
                                <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                                   class="text-sm text-violet-700 hover:underline">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{ $orders->links() }}
</div>
