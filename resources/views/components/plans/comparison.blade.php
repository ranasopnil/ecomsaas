@props([
    'settings',
    'sections',
    'packages',
    'currency',
    'symbol' => '',
    'currentId' => null,
    'lookingAt' => null,
    'interactive' => true,
])

@php
    $icons = [
        'coins' => 'M12 8.5c3.9 0 7-1.2 7-2.75S15.9 3 12 3 5 4.2 5 5.75 8.1 8.5 12 8.5ZM5 5.75v12.5C5 19.8 8.1 21 12 21s7-1.2 7-2.75V5.75M5 12c0 1.55 3.1 2.75 7 2.75s7-1.2 7-2.75',
        'bag' => 'M6 8h12l-1 12H7L6 8ZM9 8a3 3 0 0 1 6 0',
        'shop' => 'M4 9.5 5.5 4h13L20 9.5M4 9.5h16M4 9.5v10h16v-10M9.5 19.5v-5h5v5',
        'megaphone' => 'M4 10v4a1 1 0 0 0 1 1h3l6 4V5L8 9H5a1 1 0 0 0-1 1ZM18 9a4 4 0 0 1 0 6',
        'gear' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1v.3a2 2 0 1 1-4 0v-.2a1.6 1.6 0 0 0-2.8-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3.5 14h-.3a2 2 0 1 1 0-4h.2a1.6 1.6 0 0 0 1.1-2.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1v-.3a2 2 0 1 1 4 0v.2a1.6 1.6 0 0 0 2.8 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7h.3a2 2 0 1 1 0 4h-.2a1.6 1.6 0 0 0-1.4 1Z',
        'van' => 'M3 7h11v9H3zM14 10h4l3 3v3h-7ZM7.5 16a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6ZM17.5 16a1.8 1.8 0 1 0 0 3.6 1.8 1.8 0 0 0 0-3.6Z',
        'tag' => 'M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9-9-9ZM7.5 7.5h.01',
        'shield' => 'M12 3 4 6.2v5.3c0 4.6 3.3 8.2 8 9.5 4.7-1.3 8-4.9 8-9.5V6.2L12 3ZM9.2 12l2 2 3.6-3.8',
        'headset' => 'M4 13a8 8 0 0 1 16 0M4 13v3a2 2 0 0 0 2 2h1v-6H6a2 2 0 0 0-2 2ZM20 13v3a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2ZM17 18v1a2 2 0 0 1-2 2h-2',
        'spark' => 'm3 8 4.5 3.5L12 5l4.5 6.5L21 8l-1.8 10H4.8L3 8Z',
    ];

    // The column that wears the badge tints its whole way down the table.
    $tint = fn ($package) => $package->highlight ? 'bg-rose-50/50' : '';
@endphp

<div class="overflow-hidden rounded-3xl border border-slate-100 bg-white">

    {{-- ------------------------------------------------------------------
         The words above the table. Staff write every one of them.
    ------------------------------------------------------------------- --}}
    @if ($settings->heading || $settings->eyebrow)
        <div class="welcome relative overflow-hidden border-b border-slate-100 px-5 py-7 sm:px-8 sm:py-9">
            <div class="relative max-w-2xl">
                @if ($settings->eyebrow)
                    <span class="inline-block rounded-full bg-rose-100 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-rose-600">
                        {{ $settings->eyebrow }}
                    </span>
                @endif

                @if ($settings->heading)
                    <h2 class="mt-3 text-2xl font-bold leading-tight tracking-tight text-slate-900 sm:text-3xl">
                        {{ $settings->heading }}
                        @if ($settings->heading_accent)
                            <span class="text-rose-500">{{ $settings->heading_accent }}</span>
                        @endif
                    </h2>
                @endif

                @if ($settings->blurb)
                    <p class="mt-2.5 max-w-xl text-sm leading-relaxed text-slate-500">{{ $settings->blurb }}</p>
                @endif

                @if (filled($settings->promises))
                    <div class="mt-6 flex flex-wrap gap-x-8 gap-y-4">
                        @foreach ($settings->promises as $promise)
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-500">
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="{{ $icons[$promise['icon'] ?? 'spark'] ?? $icons['spark'] }}" />
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-[0.8rem] font-bold text-slate-800">{{ $promise['title'] ?? '' }}</span>
                                    <span class="block text-xs text-slate-500">{{ $promise['detail'] ?? '' }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($packages->isEmpty())
        <p class="px-6 py-14 text-center text-sm text-slate-500">No plan is on sale yet.</p>
    @else
        {{-- ------------------------------------------------------------------
             The table. Wide on purpose, so it scrolls inside its own card
             rather than pushing the page sideways.
        ------------------------------------------------------------------- --}}
        <div class="swipe-row overflow-x-auto">
            <table class="w-full min-w-[46rem] border-collapse text-sm">

                <thead>
                    <tr>
                        <th class="w-64 border-b border-slate-100 bg-white p-5 text-start align-bottom sm:w-72">
                            <span class="block text-base font-bold text-slate-900">Features</span>
                            <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">
                                Compare everything and find the one that fits.
                            </span>
                        </th>

                        @foreach ($packages as $package)
                            @php($price = $package->priceIn($currency))
                            @php($isMine = $currentId !== null && $package->id === $currentId)

                            <th class="relative border-b border-slate-100 p-4 text-center align-bottom {{ $tint($package) }}
                                       {{ $package->highlight ? 'border-x border-rose-100' : '' }}">

                                @if ($package->highlight && $package->badge)
                                    <span class="absolute inset-x-0 top-0 mx-auto w-fit rounded-b-lg bg-rose-500 px-3 py-1 text-[0.62rem] font-bold text-white">
                                        {{ $package->badge }}
                                    </span>
                                @endif

                                <span class="mt-3 block text-lg font-bold {{ $package->highlight ? 'text-rose-500' : 'text-slate-900' }}">
                                    {{ $package->name }}
                                </span>

                                <span class="mt-0.5 block text-xs font-normal text-slate-500">
                                    @if ($price === null)
                                        Not sold in {{ $currency }}
                                    @else
                                        <span class="font-bold text-slate-800">{{ $symbol }}{{ $price->toDisplay() }}</span>
                                        / {{ $package->billing_period === 'yearly' ? 'year' : 'month' }}
                                    @endif
                                </span>

                                <span class="mt-3 block">
                                    @if (! $interactive)
                                        <span class="block w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-400">
                                            {{ $package->cta ?: 'Choose plan' }}
                                        </span>
                                    @elseif ($isMine)
                                        <span class="block w-full rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-600">
                                            Your plan
                                        </span>
                                    @elseif ($price === null)
                                        <span class="block w-full rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-300">
                                            Unavailable
                                        </span>
                                    @else
                                        <button type="button" wire:click="look({{ $package->id }})"
                                                class="tap block w-full rounded-xl px-3 py-2 text-xs font-bold transition
                                                       {{ $package->highlight
                                                            ? 'bg-rose-500 text-white hover:bg-rose-600'
                                                            : ($lookingAt === $package->id
                                                                ? 'bg-slate-900 text-white'
                                                                : 'border border-slate-200 text-slate-700 hover:bg-slate-50') }}">
                                            {{ $lookingAt === $package->id ? 'Never mind' : ($package->cta ?: 'Choose plan') }}
                                        </button>
                                    @endif
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                @foreach ($sections as $section)
                    <tbody>
                        {{-- The coloured band that names a section --}}
                        <tr>
                            <th colspan="{{ $packages->count() + 1 }}"
                                class="border-y border-slate-100 bg-slate-50/80 px-5 py-2.5 text-start">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-rose-100 text-rose-500">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="{{ $icons[$section['group']->icon] ?? $icons['spark'] }}" />
                                        </svg>
                                    </span>
                                    <span class="text-[0.7rem] font-bold uppercase tracking-wider text-slate-700">
                                        {{ $section['group']->name }}
                                    </span>
                                    @if ($section['group']->note)
                                        <span class="text-xs font-normal text-slate-400">{{ $section['group']->note }}</span>
                                    @endif
                                </span>
                            </th>
                        </tr>

                        @foreach ($section['rows'] as $line)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-5 py-2.5 text-start text-[0.8rem] text-slate-700">
                                    {{ $line['row']->name }}
                                    @if ($line['row']->note)
                                        <span class="text-slate-400">({{ $line['row']->note }})</span>
                                    @endif
                                </td>

                                @foreach ($packages as $package)
                                    @php($cell = $line['cells'][$package->id] ?? ['kind' => 'dash', 'text' => null])

                                    <td class="px-3 py-2.5 text-center {{ $tint($package) }}
                                               {{ $package->highlight ? 'border-x border-rose-100' : '' }}">
                                        @if ($cell['kind'] === 'tick')
                                            <svg class="mx-auto h-5 w-5 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"
                                                 role="img" aria-label="Included">
                                                <path fill-rule="evenodd"
                                                      d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.7 7.7-5.4 5.4a1 1 0 0 1-1.4 0L7.3 12.5a1 1 0 1 1 1.4-1.4l1.9 1.9 4.7-4.7a1 1 0 0 1 1.4 1.4Z"
                                                      clip-rule="evenodd" />
                                            </svg>
                                        @elseif ($cell['kind'] === 'text')
                                            <span class="text-[0.8rem] font-semibold tabular-nums
                                                         {{ $package->highlight ? 'text-rose-500' : 'text-slate-800' }}">
                                                {{ $cell['text'] }}
                                            </span>
                                        @else
                                            <span class="text-slate-300" aria-label="Not included">&mdash;</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>

        @if ($sections === [])
            <p class="border-t border-slate-100 px-6 py-10 text-center text-sm text-slate-500">
                Nobody has written the comparison table yet.
            </p>
        @endif
    @endif
</div>
