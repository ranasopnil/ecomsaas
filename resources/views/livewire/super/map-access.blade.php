<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Maps</h1>
        <p class="mt-1 text-sm text-slate-500">
            Which map each shop draws its delivery area on. OpenStreetMap is free. Google charges the platform for
            every map opened, so hand it out where it is worth paying for.
        </p>
    </div>

    @if ($message !== '')
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</div>
    @endif

    @unless ($maps->isUsable('google'))
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Google Maps has no key on this server, so it cannot be handed out yet. Add <code>GOOGLE_MAPS_KEY</code>
            to the server settings and it will appear here.
        </div>
    @endunless

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">Shop</th>
                    <th class="px-5 py-3 text-start font-semibold">Country</th>
                    <th class="px-5 py-3 text-start font-semibold">Map it uses</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($shops as $shop)
                    <tr wire:key="shop-{{ $shop->id }}">
                        <td class="px-5 py-3 font-medium">{{ $shop->name }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $shop->country_code }}</td>
                        <td class="px-5 py-3">
                            <select wire:change="assign({{ $shop->id }}, $event.target.value)"
                                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm focus:border-violet-400 focus:outline-none">
                                <option value="" @selected($shop->map_provider === null)>
                                    Platform default ({{ $maps->find($maps->default())['name'] }})
                                </option>
                                @foreach ($providers as $key => $provider)
                                    <option value="{{ $key }}" @selected($shop->map_provider === $key) @disabled(! $maps->isUsable($key))>
                                        {{ $provider['name'] }}{{ $maps->isUsable($key) ? '' : ' — no key yet' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ $maps->find($maps->forShop($shop))['blurb'] }}</p>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">No shops yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
