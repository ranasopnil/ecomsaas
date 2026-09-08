@php
    $accent = $template['accent'];
    $symbol = config('currencies.'.$order->currency.'.symbol', $order->currency.' ');
    $good = $order->isPaid() || $order->payment_status === \App\Models\Order::PAYMENT_ON_DELIVERY;
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="'Order '.$order->reference.' — '.$store->name" robots="noindex">

    <main class="mx-auto max-w-3xl px-4 py-8">

        {{-- How it went --}}
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-100">
            @if ($order->isCancelled())
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 text-2xl font-bold text-rose-600">!</span>
                <h1 class="mt-4 text-xl font-bold">This order was not completed</h1>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    {{ $order->cancelled_reason ?: 'The payment did not go through.' }}
                    Nothing has been charged, and everything has gone back on the shelf.
                </p>
                <a href="{{ route('storefront.browse') }}"
                   class="mt-5 inline-block rounded-xl px-5 py-2.5 text-sm font-medium text-white"
                   style="background: {{ $accent }}">Start again</a>
            @elseif ($order->isWaitingToBePaid())
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-600">…</span>
                <h1 class="mt-4 text-xl font-bold">Waiting for your payment</h1>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    We have not heard from
                    {{ config('gateways.'.$order->payment_gateway.'.name', 'the payment service') }} yet.
                    If you have just paid, give it a moment and refresh this page.
                </p>
            @else
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-white"
                      style="background: {{ $accent }}">
                    <x-storefront.icon name="check" class="h-7 w-7" />
                </span>
                <h1 class="mt-4 text-xl font-bold">Thank you — your order is placed</h1>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    @if ($order->payment_status === \App\Models\Order::PAYMENT_ON_DELIVERY)
                        Have {{ $symbol }}{{ $order->total->toDisplay() }} ready for the rider.
                    @else
                        Your payment went through. We will start getting it ready.
                    @endif
                </p>
            @endif

            <p class="mt-5 text-xs uppercase tracking-wider text-slate-400">Order number</p>
            <p class="text-lg font-bold tracking-wide">{{ $order->reference }}</p>
            <p class="mt-1 text-xs text-slate-500">Keep this page. It is the only way back to this order.</p>
        </div>

        {{-- Where it has got to --}}
        @php($at = $order->journeyStep())
        @if ($at !== null && $at > 0)
            <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <h2 class="text-base font-bold">Where it is</h2>

                <ol class="mt-4 space-y-3">
                    @foreach (\App\Models\Order::JOURNEY as $index => $stage)
                        @continue($index === 0)
                        <li class="flex items-center gap-3 text-sm">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                  style="background: {{ $index <= $at ? $accent : '#e2e8f0' }}">
                                {{ $index <= $at ? '✓' : '' }}
                            </span>
                            <span class="{{ $index === $at ? 'font-semibold text-slate-900' : 'text-slate-500' }}">
                                {{ \App\Models\Order::labelFor($stage) }}
                            </span>
                        </li>
                    @endforeach
                </ol>

                @if ($order->courier_name)
                    <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        Sent with <span class="font-medium text-slate-900">{{ $order->courier_name }}</span>@if ($order->tracking_code),
                        consignment <span class="font-mono text-xs">{{ $order->tracking_code }}</span>@endif.
                        @if ($order->trackingUrl())
                            <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener nofollow"
                               class="font-medium underline" style="color: {{ $accent }}">Follow it</a>
                        @endif
                    </p>
                @endif
            </section>
        @endif

        @if ($order->status === \App\Models\Order::STATUS_NOT_DELIVERED)
            <section class="mt-6 rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-100">
                <p class="font-semibold">We could not deliver this one.</p>
                <p class="mt-1">{{ $order->not_delivered_reason }}</p>
                <p class="mt-2">Please call the shop and we will sort it out.</p>
            </section>
        @endif

        {{-- What was bought --}}
        <section class="mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-bold">What you ordered</h2>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                    {{ $order->statusLabel() }}
                </span>
            </div>

            <ul class="divide-y divide-slate-100">
                @foreach ($order->lines as $line)
                    <li class="flex justify-between gap-3 py-2.5 text-sm">
                        <span class="min-w-0">
                            <span class="block truncate text-slate-800">{{ $line->title() }}</span>
                            <span class="text-xs text-slate-500">
                                {{ $line->quantity }} × {{ $symbol }}{{ $line->unitPrice->toDisplay() }}
                                {{ $line->unit }}
                            </span>
                        </span>
                        <span class="shrink-0 tabular-nums">{{ $symbol }}{{ $line->lineTotal->toDisplay() }}</span>
                    </li>
                @endforeach
            </ul>

            <dl class="mt-3 space-y-2 border-t border-slate-100 pt-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Goods</dt>
                    <dd class="tabular-nums">{{ $symbol }}{{ $order->goods->toDisplay() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">
                        Delivery @if ($order->delivery_area_name) to {{ $order->delivery_area_name }} @endif
                    </dt>
                    <dd class="tabular-nums">
                        {{ $order->delivery_minor === 0 ? 'Free' : $symbol.$order->delivery->toDisplay() }}
                    </dd>
                </div>
                <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold">
                    <dt>Total</dt>
                    <dd class="tabular-nums">{{ $symbol }}{{ $order->total->toDisplay() }}</dd>
                </div>
            </dl>
        </section>

        {{-- Where it is going --}}
        <section class="mt-6 rounded-2xl bg-white p-5 text-sm shadow-sm ring-1 ring-slate-100">
            <h2 class="mb-3 text-base font-bold">Where it is going</h2>
            <p class="font-medium text-slate-900">{{ $order->customer_name }}</p>
            <p class="text-slate-600">{{ $order->customer_phone }}</p>
            <p class="mt-1 whitespace-pre-line text-slate-600">{{ $order->customer_address }}</p>
            @if ($order->customer_note)
                <p class="mt-2 text-slate-500">Note: {{ $order->customer_note }}</p>
            @endif
        </section>

        <div class="mt-6 text-center">
            <a href="{{ route('storefront.browse') }}" class="text-sm font-medium underline-offset-4 hover:underline"
               style="color: {{ $accent }}">Keep shopping</a>
        </div>
    </main>
</x-layouts.storefront>
