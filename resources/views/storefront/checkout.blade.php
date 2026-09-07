@php
    $accent = $template['accent'];
    $symbol = config('currencies.'.$store->currency.'.symbol', $store->currency.' ');
    $exponent = $store->currency_exponent;
    $areaCharges = $areas->mapWithKeys(fn ($a) => [$a->id => (int) $a->delivery_charge_minor]);
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="'Checkout — '.$store->name" robots="noindex">

    <main class="mx-auto max-w-5xl px-4 py-8"
          x-data="checkout(@js([
              'charges' => $areaCharges,
              'floor' => $breakdown['floor'],
              'usesShopCharge' => $breakdown['uses_shop_charge'],
              'goods' => $goods?->minor ?? 0,
              'exponent' => $exponent,
              'symbol' => $symbol,
              'area' => $area?->id,
          ]))">

        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-2xl font-bold">Checkout</h1>
            <a href="{{ route('storefront.basket') }}" class="text-sm font-medium underline-offset-4 hover:underline"
               style="color: {{ $accent }}">Back to basket</a>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-2xl bg-rose-50 px-5 py-4 text-sm text-rose-900 ring-1 ring-rose-100">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('storefront.checkout.place') }}" class="grid gap-6 lg:grid-cols-3">
            @csrf

            <div class="space-y-6 lg:col-span-2">

                {{-- Who it is going to --}}
                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <h2 class="mb-4 text-base font-bold">Where it goes</h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium">Your name</span>
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="120"
                                   autocomplete="name"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2"
                                   style="--tw-ring-color: {{ $accent }}">
                        </label>

                        <label class="block text-sm">
                            <span class="mb-1 block font-medium">Mobile number</span>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="30"
                                   autocomplete="tel" placeholder="01XXXXXXXXX"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2"
                                   style="--tw-ring-color: {{ $accent }}">
                        </label>
                    </div>

                    <label class="mt-4 block text-sm">
                        <span class="mb-1 block font-medium">Full address</span>
                        <textarea name="address" rows="3" required maxlength="500" autocomplete="street-address"
                                  placeholder="House, road, area — and anything that helps the rider find you"
                                  class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2"
                                  style="--tw-ring-color: {{ $accent }}">{{ old('address', $location->label()) }}</textarea>
                    </label>

                    @if ($areas->isNotEmpty())
                        <label class="mt-4 block text-sm">
                            <span class="mb-1 block font-medium">Delivery area</span>
                            <select name="delivery_area_id" x-model.number="area"
                                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2"
                                    style="--tw-ring-color: {{ $accent }}">
                                @foreach ($areas as $choice)
                                    <option value="{{ $choice->id }}" @selected(old('delivery_area_id', $area?->id) === $choice->id)>
                                        {{ $choice->name }}
                                        @if ($choice->delivery_charge_minor > 0)
                                            — {{ $symbol }}{{ number_format($choice->delivery_charge_minor / (10 ** $exponent), $exponent) }} delivery
                                        @else
                                            — free delivery
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @if ($area)
                                <span class="mt-1 block text-xs text-slate-500">
                                    Picked for you from where you are. Change it if that is wrong.
                                </span>
                            @endif
                        </label>
                    @endif

                    <label class="mt-4 block text-sm">
                        <span class="mb-1 block font-medium">Anything we should know? (optional)</span>
                        <input type="text" name="note" value="{{ old('note') }}" maxlength="500"
                               placeholder="Ring the bell twice, leave with the guard…"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2"
                               style="--tw-ring-color: {{ $accent }}">
                    </label>
                </section>

                {{-- How they are paying --}}
                <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <h2 class="mb-4 text-base font-bold">How you are paying</h2>

                    @if ($ways->isEmpty())
                        <p class="text-sm text-rose-800">
                            This shop has not finished setting up a way to take payments yet.
                        </p>
                    @else
                        <div class="space-y-2">
                            @foreach ($ways as $index => $way)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition hover:bg-slate-50 has-[:checked]:border-transparent has-[:checked]:ring-2"
                                       style="--tw-ring-color: {{ $accent }}">
                                    <input type="radio" name="payment" value="{{ $way->gateway }}" class="mt-1"
                                           @checked(old('payment', $ways->first()->gateway) === $way->gateway)>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium">{{ $way->name() }}</span>
                                        <span class="block text-xs text-slate-500">
                                            {{ $way->settings['instructions'] ?? ($way->definition()['blurb'] ?? '') }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            {{-- What it comes to --}}
            <aside class="h-fit rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100 lg:sticky lg:top-20">
                <h2 class="text-base font-bold">Your order</h2>

                <ul class="mt-3 space-y-2 border-b border-slate-100 pb-3">
                    @foreach ($lines as $line)
                        <li class="flex justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate text-slate-800">{{ $line->title() }}</span>
                                <span class="text-xs text-slate-500">
                                    {{ $line->quantity }} × {{ $symbol }}{{ $line->unit()->toDisplay() }}
                                    {{ $line->product->unit }}
                                </span>
                            </span>
                            <span class="shrink-0 tabular-nums">{{ $symbol }}{{ $line->total()->toDisplay() }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Goods</dt>
                        <dd class="tabular-nums">{{ $symbol }}{{ $goods->toDisplay() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Delivery</dt>
                        <dd class="tabular-nums" x-text="money(deliveryMinor)">{{ $symbol }}{{ $delivery->toDisplay() }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold">
                        <dt>Total</dt>
                        <dd class="tabular-nums" x-text="money(totalMinor)">{{ $symbol }}{{ $goods->plus($delivery)->toDisplay() }}</dd>
                    </div>
                </dl>

                <button type="submit" @disabled($ways->isEmpty())
                        class="mt-5 w-full rounded-xl px-5 py-3 text-sm font-semibold text-white transition active:scale-[.99] disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500"
                        style="{{ $ways->isEmpty() ? '' : 'background: '.$accent }}">
                    Place order
                </button>

                <p class="mt-2 text-center text-xs text-slate-500">
                    The shop works the price out again when you press this, so this is what you pay.
                </p>
            </aside>
        </form>
    </main>
</x-layouts.storefront>
