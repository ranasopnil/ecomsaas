@php
    $accent = $template['accent'];
    $symbol = $subtotal ? config('currencies.'.$subtotal->currency.'.symbol', $subtotal->currency.' ') : '';
@endphp
<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="'Your basket — '.$store->name" robots="noindex">

    <main class="mx-auto max-w-5xl px-4 py-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-2xl font-bold">Your basket</h1>
            <a href="{{ route('storefront.browse') }}" class="text-sm font-medium underline-offset-4 hover:underline" style="color: {{ $accent }}">
                Keep shopping
            </a>
        </div>

        @if ($lines->isEmpty())
            <div class="rounded-2xl bg-white p-12 text-center shadow-sm ring-1 ring-slate-100">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-slate-400"
                      style="background: {{ $accent }}1a; color: {{ $accent }}">
                    <x-storefront.icon name="basket" class="h-7 w-7" />
                </span>
                <h2 class="mt-4 text-lg font-semibold">Nothing in here yet</h2>
                <p class="mx-auto mt-2 max-w-sm text-sm text-slate-500">Press the + on anything in the shop and it will show up here.</p>
                <a href="{{ route('storefront.browse') }}" class="mt-6 inline-block rounded-xl px-5 py-2.5 text-sm font-medium text-white"
                   style="background: {{ $accent }}">Browse the shop</a>
            </div>
        @else
            <div class="grid gap-6 lg:grid-cols-3">
                <ul class="space-y-3 lg:col-span-2">
                    @foreach ($lines as $line)
                        @php($image = $line->variant->displayImage())
                        <li class="flex gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
                            <a href="{{ route('storefront.product', $line->product->slug) }}"
                               class="block h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-slate-50">
                                @if ($image)
                                    <img src="{{ $image->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">
                                @endif
                            </a>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('storefront.product', $line->product->slug) }}"
                                   class="line-clamp-2 text-sm font-medium text-slate-900 hover:underline">{{ $line->title() }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $symbol }}{{ $line->unit()->toDisplay() }} each</p>

                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <form method="POST" action="{{ route('storefront.basket.update') }}" class="flex items-center rounded-full bg-slate-100">
                                        @csrf
                                        <input type="hidden" name="variant_id" value="{{ $line->variant->id }}">
                                        <button type="submit" name="quantity" value="{{ $line->quantity - 1 }}"
                                                class="h-8 w-8 rounded-full text-lg text-slate-600 hover:bg-slate-200" aria-label="One fewer">−</button>
                                        <span class="w-8 text-center text-sm font-semibold tabular-nums">{{ $line->quantity }}</span>
                                        <button type="submit" name="quantity" value="{{ $line->quantity + 1 }}"
                                                class="h-8 w-8 rounded-full text-lg text-slate-600 hover:bg-slate-200" aria-label="One more"
                                                @disabled($line->quantity >= \App\Services\Storefront\Basket::MAX_PER_LINE)>+</button>
                                    </form>

                                    <form method="POST" action="{{ route('storefront.basket.remove') }}">
                                        @csrf
                                        <input type="hidden" name="variant_id" value="{{ $line->variant->id }}">
                                        <button type="submit" class="text-xs text-slate-500 underline-offset-4 hover:text-rose-700 hover:underline">Remove</button>
                                    </form>
                                </div>
                            </div>

                            <p class="shrink-0 text-base font-bold tabular-nums" style="color: {{ $accent }}">
                                {{ $symbol }}{{ $line->total()->toDisplay() }}
                            </p>
                        </li>
                    @endforeach
                </ul>

                <aside class="h-fit rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <h2 class="text-base font-bold">Summary</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Items</dt>
                            <dd class="tabular-nums">{{ $lines->sum('quantity') }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold">
                            <dt>Subtotal</dt>
                            <dd class="tabular-nums">{{ $symbol }}{{ $subtotal->toDisplay() }}</dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-slate-500">Delivery is worked out at checkout.</p>

                    <button type="button" disabled
                            class="mt-5 w-full cursor-not-allowed rounded-xl bg-slate-200 px-5 py-3 text-sm font-medium text-slate-500">
                        Checkout
                    </button>
                    <p class="mt-2 text-center text-xs text-slate-500">Checkout is being built next.</p>
                </aside>
            </div>
        @endif
    </main>
</x-layouts.storefront>
