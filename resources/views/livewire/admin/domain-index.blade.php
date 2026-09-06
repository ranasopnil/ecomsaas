<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Your web address</h1>
        <p class="mt-1 text-sm text-slate-500">
            Your shop already works on its free address. Add your own domain here to use that instead.
        </p>
    </div>

    {{-- Add your own domain --}}
    <div class="card rise rise-1 p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-[16rem] flex-1">
                <label class="mb-1 block text-sm font-medium">Add a domain you own</label>
                <div class="flex gap-2">
                    <input type="text" wire:model="hostname" wire:keydown.enter="add" placeholder="myshop.com"
                           @disabled(! $canAddMore)
                           class="w-full max-w-sm rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none disabled:bg-slate-50">
                    <button type="button" wire:click="add" @disabled(! $canAddMore) class="btn btn-primary disabled:opacity-50">
                        <span wire:loading.remove wire:target="add">Add</span>
                        <span wire:loading wire:target="add">Adding…</span>
                    </button>
                </div>
                @error('hostname') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-slate-500">
                    Just the address, with no https:// in front. We add the www version for you.
                </p>
            </div>

            <p class="text-sm text-slate-500">
                @if ($allowance === null)
                    Your plan allows as many addresses as you like.
                @elseif ($allowance === 0)
                    Your plan does not include your own domain. Upgrade to use one.
                @else
                    {{ $used }} of {{ $allowance }} used on your plan.
                @endif
            </p>
        </div>
    </div>

    {{-- What to set at the company you bought the domain from --}}
    <div class="card rise rise-2 overflow-hidden">
        <div class="border-b border-slate-100 p-5">
            <h2 class="font-semibold">How to point your domain here</h2>
            <p class="mt-1 text-sm text-slate-500">
                Sign in where you bought your domain, find the DNS settings, and add these two records.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-5 py-3 text-start font-semibold">Type</th>
                        <th class="px-5 py-3 text-start font-semibold">Name</th>
                        <th class="px-5 py-3 text-start font-semibold">Points to</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ([['A', '@', 'your domain itself'], ['A', 'www', 'the www version']] as [$type, $name, $what])
                        <tr>
                            <td class="px-5 py-3"><code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs">{{ $type }}</code></td>
                            <td class="px-5 py-3">
                                <code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs">{{ $name }}</code>
                                <span class="ms-2 text-xs text-slate-500">{{ $what }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs">{{ $serverIp }}</code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="space-y-3 border-t border-slate-100 p-5 text-sm text-slate-600">
            <p class="rounded-xl bg-amber-50 px-4 py-3 text-amber-900">
                <strong>Using Cloudflare?</strong> Set both records to <strong>DNS only</strong> — the grey cloud, not the
                orange one. With the orange cloud on, your address never reaches us and the padlock cannot be set up.
            </p>
            <p>
                Changes can take a few minutes, sometimes a few hours. We keep checking on our own, and your shop starts
                answering on the address the moment it points here. The padlock is set up automatically — there is
                nothing to buy or install.
            </p>
        </div>
    </div>

    {{-- Addresses this shop answers on --}}
    <div class="card rise rise-3 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-5 py-3 text-start font-semibold">Address</th>
                    <th class="px-5 py-3 text-start font-semibold">State</th>
                    <th class="px-5 py-3 text-start font-semibold">Last look</th>
                    <th class="px-5 py-3 text-end font-semibold">&nbsp;</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach ($domains as $domain)
                    <tr wire:key="domain-{{ $domain->id }}" class="{{ $justChanged === $domain->id ? 'settled' : '' }}">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <a href="https://{{ $domain->hostname }}" target="_blank" rel="noopener"
                                   class="font-medium hover:underline">{{ $domain->hostname }}</a>
                                @if ($domain->is_primary)
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">Main</span>
                                @endif
                                @if ($domain->type === 'subdomain')
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Free address</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            @php($tone = match ($domain->status) {
                                'verified' => 'bg-emerald-100 text-emerald-800',
                                'failed' => 'bg-rose-100 text-rose-800',
                                default => 'bg-amber-100 text-amber-800',
                            })
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $tone }}">
                                {{ ['pending' => 'Waiting for DNS', 'verified' => 'Working', 'failed' => 'Not working'][$domain->status] }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-xs text-slate-500">
                            @if ($domain->type === 'subdomain')
                                Always on
                            @elseif ($domain->last_checked_at)
                                <span class="block">{{ $domain->last_checked_at->diffForHumans() }}</span>
                                <span class="block max-w-xs">{{ $domain->last_check_result }}</span>
                            @else
                                Not looked yet
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex justify-end gap-2">
                                @if ($domain->type === 'custom')
                                    <button wire:click="checkNow({{ $domain->id }})" class="btn btn-quiet !px-3 !py-1.5">
                                        <span wire:loading.remove wire:target="checkNow({{ $domain->id }})">Check now</span>
                                        <span wire:loading wire:target="checkNow({{ $domain->id }})">Looking…</span>
                                    </button>
                                @endif

                                @if (! $domain->is_primary && $domain->isUsable())
                                    <button wire:click="makePrimary({{ $domain->id }})" class="btn btn-quiet !px-3 !py-1.5">
                                        Make main
                                    </button>
                                @endif

                                @if ($domain->type === 'custom')
                                    <button wire:click="remove({{ $domain->id }})"
                                            wire:confirm="Remove {{ $domain->hostname }}? Your shop will stop answering on it."
                                            class="btn !px-3 !py-1.5 border border-rose-200 text-rose-700 hover:bg-rose-50">
                                        Remove
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
