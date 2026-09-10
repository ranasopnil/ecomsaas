@php
    use App\Models\Subscription;
@endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Money due</h1>
            <p class="mt-1 text-sm text-slate-500">
                What shops say they have sent, and who has run out of time. Confirming a payment here is the
                only thing that carries a shop's plan forward.
            </p>
        </div>

        <div class="flex gap-1">
            @foreach (['waiting' => 'Waiting to be checked', 'all' => 'Everything'] as $key => $label)
                <button type="button" wire:click="$set('show', '{{ $key }}')"
                        class="rounded-xl px-3 py-1.5 text-sm transition
                               {{ $show === $key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $label }}
                    @if ($key === 'waiting' && $waitingCount > 0)
                        <span class="ms-1 rounded-full bg-amber-100 px-1.5 text-xs text-amber-900">{{ $waitingCount }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    @if ($message !== '')
        <div class="rounded-xl px-4 py-3 text-sm
                    {{ $messageType === 'error' ? 'bg-rose-50 text-rose-900' : 'bg-emerald-50 text-emerald-900' }}">
            {{ $message }}
        </div>
    @endif

    {{-- What shops say they have sent --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold">Payments to check</h2>
            <p class="mt-0.5 text-sm text-slate-500">
                Look for the transaction number in your own bKash or bank statement, then say what you found.
            </p>
        </div>

        @if ($payments->isEmpty())
            <div class="p-10 text-center">
                <h3 class="text-base font-semibold">Nothing waiting</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                    When a shop tells us it has paid, it appears here.
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach ($payments as $payment)
                    @php($shop = $shops->get($payment->tenant_id))
                    @php($symbol = config('currencies.'.$payment->currency.'.symbol', $payment->currency.' '))

                    <div wire:key="pay-{{ $payment->id }}" class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $shop?->name ?? 'A shop that has gone' }}</p>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    {{ $payment->purposeLabel() }} · {{ $payment->methodLabel() }}
                                    @if ($payment->reference)
                                        · <span class="font-mono text-xs">{{ $payment->reference }}</span>
                                    @endif
                                </p>
                                @if ($payment->note)
                                    <p class="mt-1 text-sm text-slate-600">{{ $payment->note }}</p>
                                @endif
                                <p class="mt-1 text-xs text-slate-400">
                                    Told us {{ ($payment->claimed_at ?? $payment->created_at)->diffForHumans() }}
                                    @if ($payment->confirmed_at)
                                        · decided {{ $payment->confirmed_at->format('j M Y') }}
                                        @if ($payment->confirmedBy) by {{ $payment->confirmedBy->name }} @endif
                                    @endif
                                </p>
                                @if ($payment->decision_note)
                                    <p class="mt-1 text-sm text-slate-500">&ldquo;{{ $payment->decision_note }}&rdquo;</p>
                                @endif
                            </div>

                            <div class="text-end">
                                <p class="text-xl font-bold tabular-nums">
                                    {{ $symbol }}{{ $payment->amount->toDisplay() }}
                                </p>
                                @php($tone = match ($payment->status) {
                                    'confirmed' => 'bg-emerald-100 text-emerald-800',
                                    'rejected' => 'bg-rose-100 text-rose-800',
                                    default => 'bg-amber-100 text-amber-900',
                                })
                                <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs {{ $tone }}">
                                    {{ $payment->statusLabel() }}
                                </span>
                            </div>
                        </div>

                        @if ($payment->isWaiting())
                            @if ($deciding === $payment->id)
                                <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500">
                                            A note (the shop sees this)
                                        </label>
                                        <input type="text" wire:model="note" maxlength="250"
                                               placeholder="Found it in the 6 September bKash statement"
                                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-blue-400 focus:outline-none">
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="button" wire:click="confirm({{ $payment->id }})"
                                                class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                            I found the money
                                        </button>
                                        <button type="button" wire:click="reject({{ $payment->id }})"
                                                class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">
                                            I could not find it
                                        </button>
                                        <button type="button" wire:click="decide({{ $payment->id }})"
                                                class="text-sm text-slate-500 hover:text-slate-900">Never mind</button>
                                    </div>
                                </div>
                            @else
                                <button type="button" wire:click="decide({{ $payment->id }})"
                                        class="mt-3 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                    Check this one
                                </button>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Who is running out --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="font-semibold">Running out</h2>
            <p class="mt-0.5 text-sm text-slate-500">
                Shops on a trial or past their renewal date. A shop that does not pay keeps its storefront open;
                its dashboard closes {{ $graceDays }} days after the date.
            </p>
        </div>

        @if ($due->isEmpty())
            <div class="p-10 text-center text-sm text-slate-500">Everybody is paid up.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Shop</th>
                            <th class="px-5 py-3">Plan</th>
                            <th class="px-5 py-3">State</th>
                            <th class="px-5 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($due as $subscription)
                            <tr wire:key="due-{{ $subscription->id }}">
                                <td class="px-5 py-3 font-medium">
                                    {{ $dueShops->get($subscription->tenant_id)?->name ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $subscription->package->name }}</td>
                                <td class="px-5 py-3">
                                    @if ($subscription->isLocked())
                                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-800">Dashboard closed</span>
                                    @elseif ($subscription->isPastDue())
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900">
                                            Payment due · {{ $subscription->daysLeft() }} days of grace left
                                        </span>
                                    @else
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-800">On trial</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">
                                    {{ ($subscription->status === Subscription::STATUS_TRIALING
                                        ? $subscription->trial_ends_at
                                        : $subscription->current_period_ends_at)?->format('j M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
