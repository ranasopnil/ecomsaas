@php
    $symbol = fn ($order) => config('currencies.'.$order->currency.'.symbol', $order->currency.' ');
@endphp

<div class="space-y-6">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold">Orders</h1>
            <p class="text-sm text-slate-500">
                {{ $takenToday }} {{ $takenToday === 1 ? 'order' : 'orders' }} today.
                @if ($waiting > 0)
                    {{ $waiting }} still waiting on a payment.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="show" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="real">Real orders</option>
                <option value="unfinished">Unfinished and cancelled</option>
                <option value="all">Everything</option>
            </select>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, number or order no."
                   class="w-56 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        </div>
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
                        <th class="px-4 py-3">How they paid</th>
                        <th class="px-4 py-3 text-end">Total</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($orders as $order)
                        <tr class="{{ $order->status === \App\Models\Order::STATUS_PLACED ? '' : 'opacity-60' }}">
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
                            <td class="px-4 py-3 text-slate-600">{{ $order->delivery_area_name ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ $order->statusLabel() }}</span>
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
