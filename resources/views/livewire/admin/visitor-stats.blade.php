@php
    $change = $visits['change'];

    $people = fn (int $count) => number_format($count).' '.($count === 1 ? 'person' : 'people');

    $boxes = [
        [
            'label' => 'Here right now',
            'value' => number_format($visits['rightNow']),
            'note' => $visits['rightNow'] === 1
                ? 'Somebody is looking at your shop'
                : ($visits['rightNow'] === 0
                    ? 'Nobody is on your shop at the moment'
                    : 'People looking at your shop now'),
            'accent' => 'from-emerald-500 to-green-700',
            'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM8.5 12.5 11 15l4.5-5',
            'live' => true,
            'change' => null,
        ],
        [
            'label' => 'Visits today',
            'value' => number_format($visits['today']['visits']),
            'note' => 'From '.$people($visits['today']['visitors']),
            'accent' => 'from-rose-600 to-indigo-700',
            'icon' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
            'live' => false,
            'change' => $change,
        ],
        [
            'label' => 'Visits yesterday',
            'value' => number_format($visits['yesterday']['visits']),
            'note' => 'From '.$people($visits['yesterday']['visitors']),
            'accent' => 'from-slate-600 to-slate-800',
            'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
            'live' => false,
            'change' => null,
        ],
        [
            'label' => 'Visits in '.$visits['monthName'],
            'value' => number_format($visits['month']['visits']),
            'note' => 'From '.$people($visits['month']['visitors']),
            'accent' => 'from-sky-500 to-blue-700',
            'icon' => 'M4 5h16v16H4zM4 10h16M9 3v4M15 3v4',
            'live' => false,
            'change' => null,
        ],
    ];
@endphp

<div class="space-y-2" wire:poll.15s>
    <div class="rise rise-2 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($boxes as $box)
            <div class="card card-hover relative overflow-hidden p-5">
                {{-- A wash of colour behind the corner, so each box reads apart at a glance. --}}
                <div class="pointer-events-none absolute -end-8 -top-10 h-28 w-28 rounded-full bg-gradient-to-br {{ $box['accent'] }} opacity-[.07] blur-2xl"></div>

                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br {{ $box['accent'] }} text-white">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $box['icon'] }}" />
                            </svg>
                        </span>
                        <span class="text-sm font-medium text-slate-500">{{ $box['label'] }}</span>
                    </div>

                    @if ($box['live'] && $visits['rightNow'] > 0)
                        <span class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600"></span>
                            </span>
                            Live
                        </span>
                    @endif

                    @if ($box['change'] !== null)
                        <span class="flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold
                                     {{ $box['change'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $box['change'] >= 0 ? 'm5 15 7-7 7 7' : 'm5 9 7 7 7-7' }}" />
                            </svg>
                            {{ abs($box['change']) }}%
                        </span>
                    @endif
                </div>

                <p class="mt-3 text-2xl font-bold tabular-nums">{{ $box['value'] }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $box['note'] }}</p>
            </div>
        @endforeach
    </div>

    <p class="rise rise-2 text-xs text-slate-400">
        Visits to your shopfront, counted in your shop's own day. Your own visits while signed in are not counted,
        and neither are search engines. Nothing about a visitor is stored.
    </p>
</div>
