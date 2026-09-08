@php
    $socials = [
        ['field' => 'facebook_url', 'label' => 'Facebook', 'hint' => 'facebook.com/yourshop'],
        ['field' => 'instagram_url', 'label' => 'Instagram', 'hint' => 'instagram.com/yourshop'],
        ['field' => 'youtube_url', 'label' => 'YouTube', 'hint' => 'youtube.com/@yourshop'],
        ['field' => 'tiktok_url', 'label' => 'TikTok', 'hint' => 'tiktok.com/@yourshop'],
        ['field' => 'x_url', 'label' => 'X (Twitter)', 'hint' => 'x.com/yourshop'],
        ['field' => 'linkedin_url', 'label' => 'LinkedIn', 'hint' => 'linkedin.com/company/yourshop'],
    ];

    $pages = [
        ['slug' => 'about-us', 'field' => 'about_us', 'title' => 'About us',
         'hint' => 'Who you are, how long you have been selling, why people should buy from you.'],
        ['slug' => 'privacy-policy', 'field' => 'privacy_policy', 'title' => 'Privacy policy',
         'hint' => 'What you collect about a customer, why, and who else sees it. Most payment providers ask for this.'],
        ['slug' => 'refund-policy', 'field' => 'refund_policy', 'title' => 'Refund policy',
         'hint' => 'How many days a customer has, what condition goods must come back in, how the money goes back.'],
        ['slug' => 'delivery', 'field' => 'shipping_policy', 'title' => 'Delivery',
         'hint' => 'How long delivery takes, what it costs, where you deliver to.'],
        ['slug' => 'terms', 'field' => 'terms', 'title' => 'Terms and conditions',
         'hint' => 'The rules of buying from you. Ordinary words are fine.'],
    ];
@endphp

<div class="space-y-6">
    <div class="rise flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Footer &amp; pages</h1>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">
                What sits at the bottom of every page of your shop — your address, where else people can find you,
                and the pages a customer reads before they buy. Leave anything blank and it simply does not appear.
            </p>
        </div>
        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="btn btn-quiet">
            See it on your shop
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M7 17 17 7M9 7h8v8" />
            </svg>
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- What the shop says about itself --}}
        <div class="card rise rise-1 p-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">About your shop</h2>

            <div class="grid gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">A line or two about the shop</label>
                    <textarea wire:model="about" rows="3" maxlength="400"
                              placeholder="Fresh groceries and daily needs, delivered across Dhaka since 2019."
                              class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none"></textarea>
                    <p class="mt-1 text-xs text-slate-500">Shown next to your shop name at the bottom of every page.</p>
                    @error('about') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">The small line at the very bottom</label>
                    <input type="text" wire:model="copyright" maxlength="160" placeholder="All rights reserved."
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">
                        Your shop name and this year are always shown before it.
                    </p>
                    @error('copyright') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- How to reach the shop --}}
        <div class="card rise rise-2 p-6">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">How customers reach you</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Shop address</label>
                    <textarea wire:model="address" rows="2" maxlength="300"
                              placeholder="House 12, Road 5, Dhanmondi, Dhaka 1205"
                              class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none"></textarea>
                    @error('address') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Phone</label>
                    <input type="text" wire:model="phone" maxlength="40" placeholder="+880 1712 345678"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    @error('phone') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Contact email</label>
                    <input type="email" wire:model="contact_email" maxlength="160" placeholder="hello@yourshop.com"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    @error('contact_email') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Opening hours</label>
                    <input type="text" wire:model="opening_hours" maxlength="120" placeholder="Saturday to Thursday, 9am – 9pm"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    @error('opening_hours') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Where else the shop is --}}
        <div class="card rise rise-3 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Where else people find you</h2>
            <p class="mb-4 mt-1 text-sm text-slate-500">
                Paste the link from your browser. Only the ones you fill in are shown.
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($socials as $social)
                    <div wire:key="social-{{ $social['field'] }}">
                        <label class="mb-1 block text-sm font-medium">{{ $social['label'] }}</label>
                        <input type="text" wire:model="{{ $social['field'] }}" maxlength="200" placeholder="{{ $social['hint'] }}"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        @error($social['field']) <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                <div>
                    <label class="mb-1 block text-sm font-medium">WhatsApp number</label>
                    <input type="text" wire:model="whatsapp_number" maxlength="24" placeholder="+880 1712 345678"
                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">Becomes a button that opens a chat with you.</p>
                    @error('whatsapp_number') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- The pages a customer reads before buying --}}
        <div class="card rise rise-4 p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Your pages</h2>
            <p class="mb-4 mt-1 text-sm text-slate-500">
                Write them in your own words. Each one you write appears at the bottom of your shop as a link.
                A page you leave empty does not exist on your shop at all.
            </p>

            <div class="divide-y divide-slate-100">
                @foreach ($pages as $page)
                    @php($written = trim((string) $this->{$page['field']}) !== '')
                    <div wire:key="page-{{ $page['slug'] }}" class="py-4 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                                         {{ $written ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 3h9l3.5 3.5V21H6z" /><path d="M9 12h6M9 16h4" />
                                </svg>
                            </span>

                            <div class="min-w-40 flex-1">
                                <p class="font-medium">{{ $page['title'] }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $written ? 'Written — shown on your shop' : 'Not written yet' }}
                                </p>
                            </div>

                            @if ($written)
                                <a href="{{ url('/pages/'.$page['slug']) }}" target="_blank" rel="noopener"
                                   class="btn btn-quiet !px-3 !py-1.5">View</a>
                            @endif

                            <button type="button" wire:click="writePage('{{ $page['slug'] }}')"
                                    class="btn {{ $openPage === $page['slug'] ? 'btn-quiet' : 'btn-primary' }} !px-3 !py-1.5">
                                {{ $openPage === $page['slug'] ? 'Close' : ($written ? 'Edit' : 'Write it') }}
                            </button>
                        </div>

                        @if ($openPage === $page['slug'])
                            <div class="mt-3">
                                <p class="mb-2 text-xs text-slate-500">{{ $page['hint'] }}</p>
                                <textarea wire:model="{{ $page['field'] }}" rows="12" maxlength="20000"
                                          class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm leading-relaxed focus:border-violet-400 focus:outline-none"></textarea>
                                <p class="mt-1 text-xs text-slate-500">
                                    Plain writing. Leave a blank line between paragraphs — that is how it appears on your shop.
                                </p>
                                @error($page['field']) <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Saving --}}
        <div class="sticky bottom-4 z-10">
            <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
                <p class="text-sm text-slate-500">
                    Changes show on your shop as soon as you save.
                </p>
                <div class="flex items-center gap-3">
                    <span wire:loading wire:target="save" class="text-sm text-slate-500">Saving…</span>
                    <button type="submit" class="btn btn-primary">Save footer</button>
                </div>
            </div>
        </div>
    </form>
</div>
