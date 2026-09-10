<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Shop look</h1>
        <p class="mt-1 text-sm text-slate-500">
            How your shop appears to customers. Change it whenever you like — your products, prices and
            photos stay exactly as they are.
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($templates as $key => $template)
            <div wire:key="tpl-{{ $key }}"
                 class="card p-5 {{ $template['chosen'] ? 'ring-2 ring-offset-2' : 'card-hover' }} {{ $justChanged === $key ? 'settled' : '' }}"
                 @style(['--tw-ring-color: '.$template['accent'] => $template['chosen']])>

                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="h-3 w-3 rounded-full" style="background: {{ $template['accent'] }}"></span>
                            <h2 class="font-semibold">{{ $template['name'] }}</h2>
                            {{-- Its one unchanging name, for when you ask us about it --}}
                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[0.65rem] text-slate-500"
                                  title="The name this look goes by">{{ $key }}</span>
                            @if ($template['chosen'])
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">In use</span>
                            @endif
                            @unless ($template['included'])
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Not in your plan</span>
                            @endunless
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $template['blurb'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">Best for {{ strtolower($template['best_for']) }}</p>
                    </div>
                </div>

                <ul class="mt-4 space-y-1 text-sm text-slate-600">
                    @foreach ($template['features'] as $feature)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 text-emerald-600">&check;</span>
                            <span>{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-5 flex items-center gap-3">
                    @if ($template['chosen'])
                        <span class="text-sm text-slate-500">This is what customers see now.</span>
                        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="btn btn-quiet !px-3 !py-1.5">Look at my shop</a>
                    @elseif ($template['included'])
                        <button type="button" wire:click="choose('{{ $key }}')" class="btn btn-primary !px-4 !py-2">
                            Use this look
                        </button>
                    @else
                        <button type="button" disabled class="btn btn-quiet !px-4 !py-2 cursor-not-allowed opacity-60">
                            Not in your plan
                        </button>
                        <span class="text-xs text-slate-500">Move up a plan to use this.</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
