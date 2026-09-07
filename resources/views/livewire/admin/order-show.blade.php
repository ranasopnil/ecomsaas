@php
    $symbol = config('currencies.'.$order->currency.'.symbol', $order->currency.' ');
@endphp

<div class="space-y-6">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('admin.orders.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">
                ← All orders
            </a>
            <h1 class="mt-1 text-xl font-bold">{{ $order->reference }}</h1>
            <p class="text-sm text-slate-500">
                {{ ($order->placed_at ?? $order->created_at)->format('j M Y, g:ia') }}
            </p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ $order->statusLabel() }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">What they ordered</h2>

            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->lines as $line)
                        <tr>
                            <td class="py-2.5">
                                <div class="font-medium">{{ $line->title() }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $line->quantity }} × {{ $symbol }}{{ $line->unitPrice->toDisplay() }} {{ $line->unit }}
                                </div>
                            </td>
                            <td class="py-2.5 text-end tabular-nums">{{ $symbol }}{{ $line->lineTotal->toDisplay() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Goods</dt>
                    <dd class="tabular-nums">{{ $symbol }}{{ $order->goods->toDisplay() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Delivery @if ($order->delivery_area_name) to {{ $order->delivery_area_name }} @endif</dt>
                    <dd class="tabular-nums">{{ $order->delivery_minor === 0 ? 'Free' : $symbol.$order->delivery->toDisplay() }}</dd>
                </div>
                <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold">
                    <dt>Total</dt>
                    <dd class="tabular-nums">{{ $symbol }}{{ $order->total->toDisplay() }}</dd>
                </div>
            </dl>

            <p class="mt-3 text-xs text-slate-500">
                These figures are what was agreed when the order was placed. Nothing on this screen changes them.
            </p>
        </div>

        <div class="space-y-6">
            <div class="card p-5 text-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Deliver to</h2>
                <p class="font-medium">{{ $order->customer_name }}</p>
                <p class="text-slate-600">
                    <a href="tel:{{ $order->customer_phone }}" class="hover:underline">{{ $order->customer_phone }}</a>
                </p>
                <p class="mt-2 whitespace-pre-line text-slate-600">{{ $order->customer_address }}</p>
                @if ($order->customer_note)
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-amber-900">{{ $order->customer_note }}</p>
                @endif
                @if ($order->latitude && $order->longitude)
                    <a href="https://www.google.com/maps?q={{ $order->latitude }},{{ $order->longitude }}"
                       target="_blank" rel="noopener"
                       class="mt-3 inline-block text-xs text-violet-700 hover:underline">Where they said they are</a>
                @endif
            </div>

            <div class="card p-5 text-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Payment</h2>
                <p class="font-medium">{{ config('gateways.'.$order->payment_gateway.'.name', $order->payment_gateway) }}</p>
                <p class="text-slate-600">{{ $order->statusLabel() }}</p>

                @if ($order->payment)
                    <dl class="mt-3 space-y-1 text-xs text-slate-500">
                        <div class="flex justify-between gap-3">
                            <dt>Our reference</dt>
                            <dd class="font-mono">{{ $order->payment->reference }}</dd>
                        </div>
                        @if ($order->payment->gateway_transaction_id)
                            <div class="flex justify-between gap-3">
                                <dt>bKash transaction</dt>
                                <dd class="font-mono">{{ $order->payment->gateway_transaction_id }}</dd>
                            </div>
                        @endif
                        @if ($order->payment->payer_account)
                            <div class="flex justify-between gap-3">
                                <dt>Paid from</dt>
                                <dd>{{ $order->payment->payer_account }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif

                @if ($order->cancelled_reason)
                    <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-rose-900">{{ $order->cancelled_reason }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
