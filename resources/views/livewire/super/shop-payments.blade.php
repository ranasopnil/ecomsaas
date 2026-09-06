<div class="space-y-6">
    <div>
        <a href="{{ route('super.stores.index') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-900">&larr; Shops</a>
        <h1 class="mt-1 text-2xl font-semibold">{{ $tenant->name }} — payment gateways</h1>
        <p class="mt-1 text-sm text-slate-500">
            This shop is in {{ $country }}. It gets whatever is allowed there. You can also hand it any other
            gateway here, for a shop that needs to take money from another country.
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
                    <th class="px-5 py-3 text-start font-semibold">Allowed in {{ $country }}</th>
                    <th class="px-5 py-3 text-start font-semibold">Handed to this shop</th>
                    <th class="px-5 py-3 text-start font-semibold">Shop has set it up</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($gateways as $key => $gateway)
                    @php($granted = in_array($key, $grants, true))
                    @php($method = $setUp->get($key))
                    <tr wire:key="shop-gw-{{ $key }}">
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ $gateway['name'] }}</div>
                            <div class="text-xs text-slate-500">{{ $gateway['blurb'] }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $byCountry[$key] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $byCountry[$key] ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <button type="button" wire:click="toggleGrant('{{ $key }}')"
                                    class="rounded-lg border px-3 py-1.5 text-sm
                                           {{ $granted ? 'border-rose-200 text-rose-700 hover:bg-rose-50' : 'border-slate-300 hover:bg-slate-50' }}">
                                {{ $granted ? 'Take back' : 'Hand to this shop' }}
                            </button>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-600">
                            @if ($method)
                                {{ $method->is_enabled ? 'Switched on' : 'Saved, switched off' }}
                                {{ $method->isComplete() ? '' : '· details missing' }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
