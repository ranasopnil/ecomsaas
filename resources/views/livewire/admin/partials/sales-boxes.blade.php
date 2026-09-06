@php
    // The small line inside the sales box, from the last seven days.
    $spark = $sales['spark'];
    $peak = $sales['sparkPeak'];
    $stepX = count($spark) > 1 ? 100 / (count($spark) - 1) : 100;

    $sparkPath = '';
    foreach ($spark as $i => $value) {
        $x = round($i * $stepX, 1);
        $y = round(24 - ($value / $peak) * 20, 1);
        $sparkPath .= ($i === 0 ? "M {$x} {$y}" : " L {$x} {$y}");
    }

    $change = $sales['change'];
    $boxes = [
        [
            'label' => 'Sold today',
            'value' => $store->currency.' '.$sales['today']['revenue']->toDisplay(),
            'note' => $sales['today']['units'].' item'.($sales['today']['units'] === 1 ? '' : 's').' left the shelf',
            'accent' => 'from-violet-600 to-indigo-700',
            'icon' => 'M6 6h15l-1.5 9h-12zM6 6 5 3H2M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
            'spark' => true,
            'change' => $change,
        ],
        [
            'label' => 'Sold yesterday',
            'value' => $store->currency.' '.$sales['yesterday']['revenue']->toDisplay(),
            'note' => $sales['yesterday']['units'].' item'.($sales['yesterday']['units'] === 1 ? '' : 's').' the day before',
            'accent' => 'from-slate-600 to-slate-800',
            'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
            'spark' => false,
            'change' => null,
        ],
        [
            'label' => 'Profit today',
            'value' => $store->currency.' '.$sales['today']['profit']->toDisplay(),
            'note' => $sales['today']['revenue']->minor > 0
                ? round($sales['today']['profit']->minor / $sales['today']['revenue']->minor * 100, 1).'% of what you sold'
                : 'Nothing sold yet today',
            'accent' => 'from-emerald-500 to-teal-700',
            'icon' => 'M4 19V9M10 19V5M16 19v-7M22 19H2',
            'spark' => false,
            'change' => null,
        ],
    ];
@endphp

<div class="rise rise-2 grid gap-4 lg:grid-cols-3">
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

            @if ($box['spark'] && array_sum($spark) > 0)
                <svg viewBox="0 0 100 26" preserveAspectRatio="none" class="mt-3 h-8 w-full">
                    <path d="{{ $sparkPath }}" fill="none" stroke="#5b3df5" stroke-width="1.6"
                          stroke-linecap="round" stroke-linejoin="round" opacity=".85" />
                </svg>
                <p class="text-[0.7rem] text-slate-400">Items sold, last seven days</p>
            @endif
        </div>
    @endforeach
</div>

<p class="rise rise-2 -mt-2 text-xs text-slate-400">
    Sales come from stock recorded as sold, valued at today's prices. When the checkout is built they will come from real orders.
</p>
