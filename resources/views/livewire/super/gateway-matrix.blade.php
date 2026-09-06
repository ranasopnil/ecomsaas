<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Payment gateways</h1>
        <p class="mt-1 text-sm text-slate-500">
            Which ways of taking money are offered in which country. A shop sees only what is allowed where it is,
            plus anything you hand it directly from the Shops screen.
        </p>
    </div>

    @if ($message !== '')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">Gateway</th>
                    @foreach ($countries as $code => $country)
                        <th class="px-3 py-3 text-center font-semibold" title="{{ $country['name'] }}">{{ $code }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($gateways as $key => $gateway)
                    <tr wire:key="gw-{{ $key }}">
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ $gateway['name'] }}</div>
                            <div class="text-xs text-slate-500">
                                {{ ['offline' => 'Cash', 'manual' => 'Confirmed by hand', 'online' => 'Online', 'platform' => 'Platform account'][$gateway['kind']] }}
                                · {{ $gateway['blurb'] }}
                            </div>
                        </td>
                        @foreach ($countries as $code => $country)
                            @php($on = $grid[$key][$code])
                            <td class="px-3 py-3 text-center">
                                <button type="button" wire:click="toggle('{{ $key }}', '{{ $code }}')"
                                        title="{{ $on ? 'Allowed' : 'Not allowed' }} in {{ $country['name'] }} — click to change"
                                        class="inline-flex h-7 w-12 items-center rounded-full p-0.5 transition
                                               {{ $on ? 'justify-end bg-emerald-500' : 'justify-start bg-slate-300' }}">
                                    <span class="h-6 w-6 rounded-full bg-white shadow"></span>
                                </button>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-slate-500">
        "Pay through the platform" is off everywhere until the platform's own payment account is set up.
    </p>
</div>
