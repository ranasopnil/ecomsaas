@php
    use App\Models\Subscription;
    use App\Models\SubscriptionAddon;

    $symbol = config('currencies.'.$shop->currency.'.symbol', $shop->currency.' ');
@endphp

<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Your plan</h1>
        <p class="mt-1 max-w-2xl text-sm text-slate-500">
            What you are paying for, how much of it you have used, and how to change it.
        </p>
    </div>

    @if ($subscription === null)
        <div class="card rise rise-1 p-8 text-center">
            <h2 class="text-lg font-semibold">You are not on a plan</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                Nothing is running out and nothing is owed. Ask us and we will put you on one.
            </p>
        </div>
    @else

        {{-- Where you stand --}}
        @if ($subscription->isLocked())
            <div class="rise rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-900">
                <p class="font-semibold">Your dashboard is closed until you pay.</p>
                <p class="mt-1">
                    Your shop is still open and still taking orders — your customers see nothing wrong, and
                    everything that comes in is waiting for you here. Tell us about your payment below and
                    it all opens again.
                </p>
            </div>
        @elseif ($subscription->isPastDue())
            <div class="rise rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-semibold">
                    Payment is due{{ $subscription->daysLeft() > 0 ? ' — '.$subscription->daysLeft().' '.($subscription->daysLeft() === 1 ? 'day' : 'days').' left' : '' }}.
                </p>
                <p class="mt-1">
                    Everything still works. If nothing arrives by
                    {{ $subscription->grace_ends_at?->format('j F') }}, this dashboard closes until it does.
                    Your shop stays open either way.
                </p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">

            {{-- The plan itself --}}
            <div class="card rise rise-1 p-6 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Your plan</p>
                        <h2 class="mt-1 text-2xl font-bold">{{ $subscription->package->name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $symbol }}{{ $subscription->price->toDisplay() }}
                            {{ $subscription->billing_period === 'yearly' ? 'a year' : 'a month' }}
                            @if ($mine->where('status', SubscriptionAddon::STATUS_ACTIVE)->isNotEmpty())
                                · plus your extras
                            @endif
                        </p>
                    </div>

                    <div class="text-end">
                        @php($tone = match (true) {
                            $subscription->isLocked() => 'bg-rose-100 text-rose-800',
                            $subscription->isPastDue() => 'bg-amber-100 text-amber-900',
                            $subscription->status === Subscription::STATUS_TRIALING => 'bg-sky-100 text-sky-800',
                            default => 'bg-emerald-100 text-emerald-800',
                        })
                        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $tone }}">
                            {{ match (true) {
                                $subscription->isLocked() => 'Dashboard closed',
                                $subscription->isPastDue() => 'Payment due',
                                $subscription->status === Subscription::STATUS_TRIALING => 'Free trial',
                                default => 'Running',
                            } }}
                        </span>

                        <p class="mt-2 text-sm text-slate-500">
                            @if ($subscription->status === Subscription::STATUS_TRIALING)
                                Trial ends {{ $subscription->trial_ends_at?->format('j F Y') }}
                            @else
                                Renews {{ $subscription->current_period_ends_at?->format('j F Y') }}
                            @endif
                        </p>
                    </div>
                </div>

                @if ($subscription->hasScheduledChange())
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm">
                        <span>
                            Moving to <b>{{ $subscription->scheduledPackage->name }}</b> on
                            {{ $subscription->scheduled_change_at?->format('j F Y') }}. You keep everything you
                            have until then.
                        </span>
                        <button type="button" wire:click="keepMyPlan" class="text-sm font-medium text-violet-700 hover:underline">
                            Stay where I am
                        </button>
                    </div>
                @endif

                @error('plan')
                    <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $message }}</p>
                @enderror
            </div>

            {{-- Paying --}}
            <div class="card rise rise-1 p-6">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Next payment</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">{{ $symbol }}{{ $due?->toDisplay() }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    Send it by bKash, Nagad, Rocket, bank transfer or by hand, then tell us here.
                </p>

                @if (! $paying)
                    <button type="button" wire:click="startPaying" class="btn btn-primary mt-4 w-full">
                        I have paid
                    </button>
                @else
                    <form wire:submit="tellUs" class="mt-4 space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">How much you sent</label>
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-slate-500">{{ $symbol }}</span>
                                <input type="text" inputmode="decimal" wire:model="amount"
                                       class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm tabular-nums focus:border-violet-400 focus:outline-none">
                            </div>
                            @error('amount') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">How you sent it</label>
                            <select wire:model="method"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                                @foreach ($methods as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Transaction number</label>
                            <input type="text" wire:model="reference" maxlength="120" placeholder="8N7A2K9QX1"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 font-mono text-xs focus:border-violet-400 focus:outline-none">
                            <p class="mt-1 text-xs text-slate-500">It helps us find your payment faster.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn btn-primary !px-4 !py-2">Tell us</button>
                            <button type="button" wire:click="cancelPaying" class="text-sm text-slate-500 hover:text-slate-900">
                                Cancel
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        {{-- How much you have used --}}
        <div class="card rise rise-2 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">What you have used</h2>

            <div class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                @foreach ($counted as $row)
                    <div wire:key="used-{{ $row['feature'] }}">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="font-medium">{{ $row['label'] }}</span>
                            <span class="tabular-nums {{ $row['full'] ? 'font-semibold text-rose-700' : 'text-slate-500' }}">
                                {{ number_format($row['used']) }}
                                @if ($row['allowance'] === null)
                                    <span class="text-slate-400">· no limit</span>
                                @else
                                    of {{ number_format($row['allowance']) }}
                                @endif
                            </span>
                        </div>
                        @if ($row['share'] !== null)
                            <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $row['full'] ? 'bg-rose-500' : ($row['share'] > 0.8 ? 'bg-amber-500' : 'bg-violet-500') }}"
                                     style="width: {{ max(2, round($row['share'] * 100)) }}%"></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                @foreach ($switches as $switch)
                    <span class="rounded-full px-3 py-1 text-xs font-medium
                                 {{ $switch['on'] ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-400 line-through' }}">
                        {{ $switch['label'] }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- The other plans --}}
        <div class="card rise rise-3 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">The other plans</h2>
            <p class="mt-1 text-sm text-slate-500">
                Moving up starts as soon as we find your payment, and you pay only the difference for the days
                you have left. Moving down waits until your plan runs out, so you keep what you have paid for.
            </p>

            <div class="mt-4 grid gap-4 md:grid-cols-3">
                @foreach ($choices as $package)
                    @php($price = $package->priceIn($shop->currency))
                    @php($isMine = $package->id === $subscription->package_id)
                    @php($isUp = $plans->isUpgrade($subscription, $package, $shop))

                    <div wire:key="plan-{{ $package->id }}"
                         class="rounded-2xl border p-4 {{ $isMine ? 'border-violet-300 bg-violet-50/40' : 'border-slate-200' }}">
                        <div class="flex items-baseline justify-between gap-2">
                            <h3 class="font-semibold">{{ $package->name }}</h3>
                            @if ($isMine)
                                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-800">Yours</span>
                            @endif
                        </div>

                        <p class="mt-1 text-xl font-bold tabular-nums">
                            {{ $symbol }}{{ $price->toDisplay() }}
                            <span class="text-xs font-normal text-slate-500">
                                {{ $package->billing_period === 'yearly' ? 'a year' : 'a month' }}
                            </span>
                        </p>

                        <ul class="mt-3 space-y-1 text-xs text-slate-600">
                            @foreach ($package->entitlements->where('enabled', true)->take(5) as $entitlement)
                                <li>
                                    {{ config('features.'.$entitlement->feature.'.label', $entitlement->feature) }}:
                                    <b>{{ $entitlement->limit_value === null ? 'no limit' : number_format($entitlement->limit_value) }}</b>
                                </li>
                            @endforeach
                        </ul>

                        @unless ($isMine)
                            @if ($lookingAt === $package->id)
                                <div class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                    @if ($isUp)
                                        <p class="text-xs text-slate-600">
                                            To move up today you would send
                                            <b>{{ $symbol }}{{ $plans->differenceToday($subscription, $package, $shop)->toDisplay() }}</b>
                                            — the difference for the days you have left. Your renewal date does not move.
                                        </p>
                                        <button type="button" wire:click="moveUp({{ $package->id }})"
                                                class="btn btn-primary w-full !px-3 !py-2">
                                            Move up to {{ $package->name }}
                                        </button>
                                    @else
                                        <p class="text-xs text-slate-600">
                                            You would move down on
                                            {{ $subscription->current_period_ends_at?->format('j F Y') }}, when
                                            {{ $subscription->package->name }} runs out. Nothing changes before then.
                                        </p>
                                        <button type="button" wire:click="moveDown({{ $package->id }})"
                                                class="btn btn-quiet w-full !px-3 !py-2">
                                            Move down to {{ $package->name }}
                                        </button>
                                    @endif
                                    <button type="button" wire:click="look({{ $package->id }})"
                                            class="w-full text-xs text-slate-500 hover:text-slate-900">
                                        Never mind
                                    </button>
                                </div>
                            @else
                                <button type="button" wire:click="look({{ $package->id }})"
                                        class="btn btn-quiet mt-3 w-full !px-3 !py-2">
                                    {{ $isUp ? 'Move up' : 'Move down' }}
                                </button>
                            @endif
                        @endunless
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Extras --}}
        @if ($addons->isNotEmpty() || $mine->isNotEmpty())
            <div class="card rise rise-4 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Extras on top of your plan</h2>
                <p class="mt-1 text-sm text-slate-500">
                    A little more of something, without moving to a bigger plan. They renew on the same date as
                    your plan, so there is still one date and one amount.
                </p>

                @if ($mine->isNotEmpty())
                    <div class="mt-4 divide-y divide-slate-100">
                        @foreach ($mine as $bought)
                            <div wire:key="mine-{{ $bought->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ $bought->addon?->what() }}{{ $bought->quantity > 1 ? ' ×'.$bought->quantity : '' }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ $symbol }}{{ $bought->total()->toDisplay() }} each renewal
                                    </p>
                                </div>
                                @if ($bought->status === SubscriptionAddon::STATUS_PENDING)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900">
                                        Waiting for your payment
                                    </span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">On</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($addons->isNotEmpty())
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($addons as $addon)
                            <div wire:key="addon-{{ $addon->id }}" class="rounded-xl border border-slate-200 p-4">
                                <p class="text-sm font-medium">{{ $addon->what() }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $symbol }}{{ $addon->priceIn($shop->currency)->toDisplay() }} a month
                                    @if ($addon->description) · {{ $addon->description }} @endif
                                </p>

                                @if ($buying === $addon->id)
                                    <div class="mt-3 flex items-center gap-2">
                                        @if ($addon->isUnits())
                                            <select wire:model="quantity"
                                                    class="rounded-xl border border-slate-200 px-2 py-1.5 text-sm">
                                                @foreach (range(1, 5) as $n)
                                                    <option value="{{ $n }}">×{{ $n }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        <button type="button" wire:click="buy({{ $addon->id }})"
                                                class="btn btn-primary !px-3 !py-1.5">Add it</button>
                                        <button type="button" wire:click="startBuying({{ $addon->id }})"
                                                class="text-xs text-slate-500 hover:text-slate-900">Never mind</button>
                                    </div>
                                @else
                                    <button type="button" wire:click="startBuying({{ $addon->id }})"
                                            class="btn btn-quiet mt-3 !px-3 !py-1.5">Add this</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- What you have paid --}}
        @if ($history->isNotEmpty())
            <div class="card rise p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">What you have paid</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="pb-2 pe-3">Told us</th>
                                <th class="pb-2 pe-3">What for</th>
                                <th class="pb-2 pe-3">How</th>
                                <th class="pb-2 pe-3 text-end">Amount</th>
                                <th class="pb-2">State</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($history as $payment)
                                <tr wire:key="paid-{{ $payment->id }}">
                                    <td class="py-2.5 pe-3 text-slate-600">
                                        {{ ($payment->claimed_at ?? $payment->created_at)->format('j M Y') }}
                                    </td>
                                    <td class="py-2.5 pe-3">
                                        {{ $payment->purposeLabel() }}
                                        @if ($payment->covers_to)
                                            <div class="text-xs text-slate-500">
                                                to {{ $payment->covers_to->format('j M Y') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pe-3 text-slate-600">
                                        {{ $payment->methodLabel() }}
                                        @if ($payment->reference)
                                            <div class="font-mono text-xs text-slate-400">{{ $payment->reference }}</div>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pe-3 text-end font-semibold tabular-nums">
                                        {{ $symbol }}{{ $payment->amount->toDisplay() }}
                                    </td>
                                    <td class="py-2.5">
                                        @php($paidTone = match ($payment->status) {
                                            'confirmed' => 'bg-emerald-100 text-emerald-800',
                                            'rejected' => 'bg-rose-100 text-rose-800',
                                            default => 'bg-slate-100 text-slate-600',
                                        })
                                        <span class="rounded-full px-2 py-0.5 text-xs {{ $paidTone }}">
                                            {{ $payment->statusLabel() }}
                                        </span>
                                        @if ($payment->decision_note)
                                            <div class="mt-0.5 text-xs text-slate-500">{{ $payment->decision_note }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
