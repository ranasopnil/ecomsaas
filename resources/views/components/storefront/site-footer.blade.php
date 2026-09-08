@props([
    'store',
    'accent' => '#16a34a',
])

@php
    $shopFooter = App\Models\StorefrontFooter::forShop();
    $socials = $shopFooter->socialLinks();
    $pages = $shopFooter->writtenPages();
    $areas = app(App\Services\Storefront\DeliveryReach::class)->areas();

    $email = trim((string) ($shopFooter->contact_email ?: $store->email));
    $phone = trim((string) $shopFooter->phone);
    $address = trim((string) $shopFooter->address);
    $hours = trim((string) $shopFooter->opening_hours);
    $about = trim((string) $shopFooter->about);
    $rights = trim((string) $shopFooter->copyright);
@endphp

{{--
    The bottom of every page of the shop.

    Everything here is what the shopkeeper typed on their own footer screen —
    the address, the phone number, where else to find them, and the pages a
    shopper wants to read before buying. Anything left blank is left out
    entirely rather than shown as an empty heading.
--}}
<footer class="mt-12 bg-slate-900 text-slate-400">
    <div class="h-1 w-full" style="background: linear-gradient(90deg, {{ $accent }} 0%, {{ $accent }}55 60%, transparent 100%)"></div>

    <div class="mx-auto max-w-6xl px-4 py-12">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Who the shop is --}}
            <div class="sm:col-span-2 lg:col-span-1">
                <a href="{{ url('/') }}" class="flex items-center gap-2">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl text-base font-bold text-white"
                          style="background: {{ $accent }}">{{ mb_substr($store->name, 0, 1) }}</span>
                    <span class="text-lg font-bold text-white">{{ $store->name }}</span>
                </a>

                @if ($about !== '')
                    <p class="mt-4 max-w-sm text-sm leading-relaxed">{{ $about }}</p>
                @endif

                @if ($socials->isNotEmpty())
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($socials as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener nofollow"
                               title="{{ $social['label'] }}" aria-label="{{ $store->name }} on {{ $social['label'] }}"
                               class="social-chip flex h-10 w-10 items-center justify-center rounded-xl bg-white/5 text-slate-300 ring-1 ring-white/10 transition hover:-translate-y-0.5 hover:text-white"
                               style="--chip: {{ $accent }}">
                                <x-storefront.icon :name="$social['key']" class="h-5 w-5" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- How to reach the shop --}}
            @if ($address !== '' || $phone !== '' || $email !== '' || $hours !== '')
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-white">Get in touch</h2>
                    <ul class="mt-4 space-y-3 text-sm">
                        @if ($address !== '')
                            <li class="flex gap-2.5">
                                <x-storefront.icon name="pin" class="mt-0.5 h-4 w-4 shrink-0" style="color: {{ $accent }}" />
                                <span class="whitespace-pre-line">{{ $address }}</span>
                            </li>
                        @endif
                        @if ($phone !== '')
                            <li class="flex gap-2.5">
                                <x-storefront.icon name="phone" class="mt-0.5 h-4 w-4 shrink-0" style="color: {{ $accent }}" />
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="hover:text-white">{{ $phone }}</a>
                            </li>
                        @endif
                        @if ($email !== '')
                            <li class="flex gap-2.5">
                                <x-storefront.icon name="mail" class="mt-0.5 h-4 w-4 shrink-0" style="color: {{ $accent }}" />
                                <a href="mailto:{{ $email }}" class="break-all hover:text-white">{{ $email }}</a>
                            </li>
                        @endif
                        @if ($hours !== '')
                            <li class="flex gap-2.5">
                                <x-storefront.icon name="clock" class="mt-0.5 h-4 w-4 shrink-0" style="color: {{ $accent }}" />
                                <span>{{ $hours }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            {{-- Round the shop --}}
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-white">Shop</h2>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li><a href="{{ url('/') }}" class="hover:text-white">Home</a></li>
                    <li><a href="{{ route('storefront.browse') }}" class="hover:text-white">Everything we sell</a></li>
                    <li><a href="{{ route('storefront.browse') }}?offers=1" class="hover:text-white">Offers and reductions</a></li>
                    <li><a href="{{ route('storefront.basket') }}" class="hover:text-white">Your basket</a></li>
                </ul>
            </div>

            {{-- What the shopkeeper has written down --}}
            @if ($pages->isNotEmpty())
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-white">Good to know</h2>
                    <ul class="mt-4 space-y-2.5 text-sm">
                        @foreach ($pages as $page)
                            <li>
                                <a href="{{ route('storefront.page', $page['slug']) }}" class="hover:text-white">{{ $page['title'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Where the shop reaches --}}
        <div class="mt-10 flex flex-wrap items-center gap-2 rounded-2xl bg-white/5 px-4 py-3 text-sm ring-1 ring-white/10">
            <x-storefront.icon name="bike" class="h-5 w-5 shrink-0" style="color: {{ $accent }}" />
            <span>
                @if ($areas->isEmpty())
                    Delivering everywhere.
                @else
                    Delivering to {{ $areas->pluck('name')->join(', ', ' and ') }}.
                @endif
            </span>
        </div>

        {{-- The line at the very bottom --}}
        <div class="mt-8 flex flex-col gap-2 border-t border-white/10 pt-6 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ $store->name }}. {{ $rights !== '' ? $rights : 'All rights reserved.' }}</p>
            @if ($pages->isNotEmpty())
                <p class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($pages as $page)
                        <a href="{{ route('storefront.page', $page['slug']) }}" class="hover:text-white">{{ $page['title'] }}</a>
                    @endforeach
                </p>
            @endif
        </div>
    </div>
</footer>
