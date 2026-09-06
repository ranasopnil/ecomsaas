@php
    $icons = [
        'shop' => 'M3 9.5 4.5 4h15L21 9.5M3 9.5h18M3 9.5V20h18V9.5M3 9.5a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0',
        'box' => 'M12 3 3 7.5v9L12 21l9-4.5v-9zM3 7.5 12 12l9-4.5M12 12v9',
        'eye' => 'M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z M12 14.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
        'camera' => 'M4 8h3l1.5-2h7L17 8h3v11H4zM12 16.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z',
        'grid' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
        'coin' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v10M14.5 9.5a2.5 2.5 0 0 0-5 .5c0 2.5 5 1.5 5 4a2.5 2.5 0 0 1-5 .5',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3.5 9h17M3.5 15h17M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
    ];
@endphp

<div class="rise rise-1">
    @if ($setupHidden)
        {{-- Put away: one quiet line that brings it back. --}}
        <button type="button" wire:click="showSetup"
                class="card card-hover flex w-full items-center gap-3 px-5 py-3 text-start">
            <span class="relative flex h-9 w-9 shrink-0 items-center justify-center">
                <svg viewBox="0 0 36 36" class="h-9 w-9 -rotate-90">
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="#e9e5ff" stroke-width="4" />
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="#5b3df5" stroke-width="4" stroke-linecap="round"
                            stroke-dasharray="{{ round($health['percent'] * 0.974, 2) }} 100" />
                </svg>
                <span class="absolute text-[0.65rem] font-bold">{{ $health['percent'] }}</span>
            </span>
            <span class="flex-1">
                <span class="block text-sm font-semibold">Setting up your shop</span>
                <span class="block text-xs text-slate-500">
                    {{ $health['done'] }} of {{ $health['total'] }} done · show the steps
                </span>
            </span>
            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>
    @else
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#3b1e9e] via-[#5b3df5] to-[#7c3aed] text-white shadow-[0_18px_40px_-18px_rgba(91,61,245,.65)]">
            {{-- A soft glow behind, so the box has depth without a busy pattern. --}}
            <div class="pointer-events-none absolute -end-16 -top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -start-10 bottom-[-6rem] h-64 w-64 rounded-full bg-cyan-300/10 blur-3xl"></div>

            <div class="relative p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[.14em] text-violet-200">Getting started</p>
                        <h2 class="mt-1 text-xl font-semibold">
                            @if ($health['done'] === $health['total'])
                                Your shop is ready
                            @else
                                {{ $health['total'] - $health['done'] }} step{{ $health['total'] - $health['done'] === 1 ? '' : 's' }} to go
                            @endif
                        </h2>
                        <p class="mt-1 max-w-lg text-sm text-violet-100">
                            @if ($health['done'] === $health['total'])
                                Everything on the list is done. This box can be put away.
                            @else
                                {{ $health['tasks'][$health['next']]['todo'] }}
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-end">
                            <p class="text-2xl font-bold leading-none tabular-nums">{{ $health['percent'] }}%</p>
                            <p class="text-xs text-violet-200">{{ $health['done'] }} of {{ $health['total'] }} done</p>
                        </div>

                        <button type="button" wire:click="hideSetup" title="Put these steps away"
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 text-white/80 transition hover:bg-white/20 hover:text-white">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <path d="M5 12h14" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- How far along, as one bar. --}}
                <div class="mt-5 h-1.5 w-full overflow-hidden rounded-full bg-white/15">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-300 to-cyan-200"
                         style="width: {{ $health['percent'] }}%; transition: width .9s cubic-bezier(.4,0,.2,1)"></div>
                </div>

                {{-- The steps themselves. --}}
                <ol class="mt-6 flex snap-x gap-3 overflow-x-auto pb-1">
                    @foreach ($health['tasks'] as $index => $task)
                        @php($isNext = $health['next'] === $index)
                        @php($inner = 'group flex h-full w-full flex-col gap-2 rounded-xl border p-3 text-start transition duration-300 '
                            .($task['done']
                                ? 'border-white/15 bg-white/10'
                                : ($isNext
                                    ? 'border-white/60 bg-white/20 shadow-[0_0_0_3px_rgba(255,255,255,.12)]'
                                    : 'border-white/10 bg-white/[.04]')))
                        <li class="relative flex min-w-[10.5rem] flex-1 snap-start">
                            @if ($index > 0)
                                <span class="absolute -start-3 top-9 hidden h-px w-3 sm:block
                                             {{ $task['done'] ? 'bg-emerald-300/70' : 'bg-white/20' }}"></span>
                            @endif

                            @if (! $task['done'] && $task['route'])
                                <a href="{{ route($task['route']) }}" wire:navigate class="{{ $inner }} hover:-translate-y-0.5 hover:bg-white/25">
                            @else
                                <div class="{{ $inner }}">
                            @endif

                                <span class="flex items-center justify-between">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-lg
                                                 {{ $task['done'] ? 'bg-emerald-300 text-emerald-900' : 'bg-white/15 text-white' }}">
                                        @if ($task['done'])
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m5 13 4 4L19 7" />
                                            </svg>
                                        @else
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                 stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="{{ $icons[$task['icon']] }}" />
                                            </svg>
                                        @endif
                                    </span>

                                    @if ($isNext)
                                        <span class="rounded-full bg-white px-2 py-0.5 text-[0.65rem] font-bold uppercase tracking-wide text-violet-700">
                                            Next
                                        </span>
                                    @else
                                        <span class="text-[0.7rem] font-semibold text-white/45">{{ $index + 1 }}</span>
                                    @endif
                                </span>

                                <span class="block text-sm font-semibold leading-snug">{{ $task['label'] }}</span>
                                <span class="block text-xs leading-snug {{ $task['done'] ? 'text-violet-200' : 'text-violet-100' }}">
                                    {{ $task['done'] ? $task['hint'] : $task['todo'] }}
                                </span>

                            @if (! $task['done'] && $task['route'])
                                </a>
                            @else
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    @endif
</div>
