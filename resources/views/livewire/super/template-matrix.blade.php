<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Shop templates</h1>
        <p class="mt-1 text-sm text-slate-500">
            Which shop front each plan includes. Every shop sees all of them, so the ones a shop cannot use
            read as a reason to move up a plan rather than something missing.
        </p>
    </div>

    @if ($message !== '')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</div>
    @endif

    @if ($packages->isEmpty())
        <div class="rounded-xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
            There are no plans yet. <a href="{{ route('super.packages.index') }}" wire:navigate class="underline">Add one first.</a>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-5 py-3 text-start font-semibold">Template</th>
                        @foreach ($packages as $package)
                            <th class="px-3 py-3 text-center font-semibold">{{ $package->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($templates as $key => $template)
                        <tr wire:key="tpl-{{ $key }}">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-3 w-3 rounded-full" style="background: {{ $template['accent'] }}"></span>
                                    <span class="font-medium">{{ $template['name'] }}</span>
                                    @if ($template['always'])
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">In every plan</span>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ $template['blurb'] }}</div>
                            </td>
                            @foreach ($packages as $package)
                                @php($on = $grid[$key][$package->id])
                                <td class="px-3 py-3 text-center">
                                    <button type="button" wire:click="toggle('{{ $key }}', {{ $package->id }})"
                                            @disabled($template['always'])
                                            title="{{ $template['always'] ? 'Comes with every plan' : ($on ? 'Included' : 'Not included').' in '.$package->name }}"
                                            class="inline-flex h-7 w-12 items-center rounded-full p-0.5 transition
                                                   {{ $on ? 'justify-end bg-emerald-500' : 'justify-start bg-slate-300' }}
                                                   {{ $template['always'] ? 'cursor-not-allowed opacity-60' : '' }}">
                                        <span class="h-6 w-6 rounded-full bg-white shadow"></span>
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="text-xs text-slate-500">
        A shop that moves down to a plan without its chosen template keeps its products and falls back to Classic.
        Nothing is lost, and moving back up restores it.
    </p>
</div>
