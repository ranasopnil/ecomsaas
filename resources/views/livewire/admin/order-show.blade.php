@php
    use App\Models\Order;

    $symbol = config('currencies.'.$order->currency.'.symbol', $order->currency.' ');

    $tone = match ($order->status) {
        Order::STATUS_DELIVERED => 'bg-emerald-100 text-emerald-800',
        Order::STATUS_CANCELLED => 'bg-rose-100 text-rose-800',
        Order::STATUS_NOT_DELIVERED => 'bg-amber-100 text-amber-900',
        Order::STATUS_PENDING_PAYMENT => 'bg-slate-100 text-slate-600',
        Order::STATUS_PLACED => 'bg-violet-100 text-violet-800',
        default => 'bg-sky-100 text-sky-800',
    };

    $at = $order->journeyStep();
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
        <span class="rounded-full px-3 py-1 text-sm font-medium {{ $tone }}">{{ $order->statusLabel() }}</span>
    </div>

    {{-- Where the parcel has got to --}}
    @if ($at !== null)
        <div class="card p-5">
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-3">
                @foreach (Order::JOURNEY as $index => $stage)
                    <li class="flex items-center gap-2">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold
                                     {{ $index <= $at ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                            {{ $index < $at ? '✓' : $index + 1 }}
                        </span>
                        <span class="text-sm {{ $index === $at ? 'font-semibold' : 'text-slate-500' }}">
                            {{ Order::labelFor($stage) }}
                        </span>
                    </li>
                    @if (! $loop->last)
                        <li class="hidden h-px w-6 bg-slate-200 sm:block"></li>
                    @endif
                @endforeach
            </ol>
        </div>
    @endif

    @if ($order->status === Order::STATUS_NOT_DELIVERED)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <span class="font-semibold">Not delivered.</span>
            {{ $order->not_delivered_reason }}
        </div>
    @elseif ($order->isCancelled())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <span class="font-semibold">Cancelled.</span>
            {{ $order->cancelled_reason }}
            @if ($order->isPaid())
                <span class="mt-1 block">
                    This one was already paid for. The stock is back on the shelf; giving the money back is a
                    separate step, on the payment.
                </span>
            @endif
        </div>
    @endif

    {{-- What happens next --}}
    @if ($steps !== [])
        <div class="card p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">What happens next</h2>

            @if ($step === '')
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($steps as $next)
                        <button type="button" wire:click="choose('{{ $next['status'] }}')"
                                wire:loading.attr="disabled"
                                class="btn {{ $next['tone'] === 'bad' ? 'btn-quiet !text-rose-700' : 'btn-primary' }} !px-4 !py-2">
                            {{ $next['label'] }}
                        </button>
                    @endforeach
                </div>

                @if ($order->status === Order::STATUS_PLACED)
                    <p class="mt-3 text-xs text-slate-500">
                        Approving tells the customer you are making it. Rejecting puts everything on this order back
                        on the shelf.
                    </p>
                @endif
            @else
                <form wire:submit="take('{{ $step }}')" class="mt-3 space-y-3">
                    @if ($needs === 'courier')
                        @if ($couriers->isEmpty())
                            <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                You have not named any couriers yet.
                                <a href="{{ route('admin.couriers.index') }}" wire:navigate class="font-semibold underline">
                                    Add one first
                                </a>, then come back.
                            </p>
                        @else
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Which courier</label>
                                    <select wire:model="courier_id"
                                            class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                                        @foreach ($couriers as $courier)
                                            <option value="{{ $courier->id }}">{{ $courier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-500">
                                        Consignment number (optional)
                                    </label>
                                    <input type="text" wire:model="tracking_code" maxlength="80"
                                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                                    <p class="mt-1 text-xs text-slate-500">
                                        The customer sees this, and can follow the parcel if the courier has a page for it.
                                    </p>
                                </div>
                            </div>
                        @endif
                    @endif

                    @if ($needs === 'reason')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">
                                {{ $step === Order::STATUS_CANCELLED ? 'Why are you turning it down?' : 'Why did it not arrive?' }}
                            </label>
                            <textarea wire:model="reason" rows="2" maxlength="1000"
                                      placeholder="{{ $step === Order::STATUS_CANCELLED
                                          ? 'Out of stock, customer changed their mind, cannot reach the address…'
                                          : 'Nobody at home, phone switched off, customer refused it…' }}"
                                      class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none"></textarea>
                            <p class="mt-1 text-xs text-slate-500">
                                Kept on the order for good. Whoever opens it next will see why.
                            </p>
                        </div>
                    @endif

                    @error('step') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror

                    <div class="flex items-center gap-2">
                        <button type="submit" class="btn btn-primary !px-4 !py-2"
                                @disabled($needs === 'courier' && $couriers->isEmpty())>
                            Confirm
                        </button>
                        <button type="button" wire:click="cancelStep" class="text-sm text-slate-500 hover:text-slate-900">
                            Never mind
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endif

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

            @if ($order->courier_name)
                <div class="card p-5 text-sm">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Courier</h2>
                    <p class="font-medium">{{ $order->courier_name }}</p>
                    @if ($order->courier?->phone)
                        <p class="text-slate-600">
                            <a href="tel:{{ $order->courier->phone }}" class="hover:underline">{{ $order->courier->phone }}</a>
                        </p>
                    @endif
                    @if ($order->tracking_code)
                        <p class="mt-2 text-xs text-slate-500">Consignment number</p>
                        <p class="font-mono text-xs">{{ $order->tracking_code }}</p>
                        @if ($order->trackingUrl())
                            <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener"
                               class="mt-2 inline-block text-xs text-violet-700 hover:underline">Follow it on their site</a>
                        @endif
                    @endif
                    @if ($order->handed_over_at)
                        <p class="mt-2 text-xs text-slate-500">
                            Handed over {{ $order->handed_over_at->format('j M, g:ia') }}
                        </p>
                    @endif
                </div>
            @endif

            <div class="card p-5 text-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Payment</h2>
                <p class="font-medium">{{ config('gateways.'.$order->payment_gateway.'.name', $order->payment_gateway) }}</p>
                <p class="text-slate-600">{{ $order->paymentLabel() }}</p>

                @if ($order->payment)
                    <dl class="mt-3 space-y-1 text-xs text-slate-500">
                        <div class="flex justify-between gap-3">
                            <dt>Our reference</dt>
                            <dd class="font-mono">{{ $order->payment->reference }}</dd>
                        </div>
                        @if ($order->payment->gateway_transaction_id)
                            <div class="flex justify-between gap-3">
                                <dt>{{ config('gateways.'.$order->payment_gateway.'.name', 'Gateway') }} transaction</dt>
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
            </div>
        </div>
    </div>

    {{-- Everything that has happened to this order --}}
    @if ($order->events->isNotEmpty())
        <div class="card p-5">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">History</h2>

            <ol class="space-y-4">
                @foreach ($order->events->reverse() as $event)
                    <li wire:key="event-{{ $event->id }}" class="flex gap-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full
                                     {{ $loop->first ? 'bg-violet-600' : 'bg-slate-300' }}"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ $event->title() }}</p>
                            @if ($event->tracking_code)
                                <p class="font-mono text-xs text-slate-500">{{ $event->tracking_code }}</p>
                            @endif
                            @if ($event->note)
                                <p class="mt-0.5 whitespace-pre-line text-sm text-slate-600">{{ $event->note }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-slate-400">
                                {{ $event->created_at->format('j M Y, g:ia') }} · by {{ $event->byWhom() }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
