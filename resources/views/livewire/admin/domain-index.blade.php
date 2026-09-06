@php
    $free = $domains->firstWhere('type', 'subdomain');
    $own = $domains->where('type', 'custom');
@endphp

<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Your web address</h1>
        <p class="mt-1 text-sm text-slate-500">
            Where customers find your shop. Your free address is always there; your own domain can sit alongside it.
        </p>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Left: the shop's addresses ---------------------------------- --}}
        <div class="space-y-6 xl:col-span-2">

            {{-- The free address, given with the shop --}}
            @if ($free)
                <div class="card rise rise-1 relative overflow-hidden p-5 {{ $justChanged === $free->id ? 'settled' : '' }}">
                    <div class="pointer-events-none absolute -end-10 -top-12 h-40 w-40 rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 opacity-[.07] blur-2xl"></div>

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 text-white">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3.5 9h17M3.5 15h17M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
                                </svg>
                            </span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="https://{{ $free->hostname }}" target="_blank" rel="noopener"
                                       class="text-lg font-semibold hover:underline">{{ $free->hostname }}</a>
                                    @if ($free->is_primary)
                                        <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">Main address</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    Your free address. It was created with your shop and is always on — nothing to set up.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Working
                            </span>
                            @if (! $free->is_primary)
                                <button wire:click="makePrimary({{ $free->id }})" class="btn btn-quiet !px-3 !py-1.5">Make main</button>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- Your own domains --}}
            <div class="card rise rise-2 overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5">
                    <div>
                        <h2 class="font-semibold">Your own domain</h2>
                        <p class="text-sm text-slate-500">
                            @if ($allowance === null)
                                Add as many as you like.
                            @elseif ($allowance === 0)
                                Not included in your plan. Upgrade to use your own domain.
                            @else
                                {{ $used }} of {{ $allowance }} used on your plan.
                            @endif
                        </p>
                    </div>
                </div>

                <div class="border-b border-slate-100 p-5">
                    <label class="mb-1 block text-sm font-medium">Add a domain you own</label>
                    <div class="flex gap-2">
                        <input type="text" wire:model="hostname" wire:keydown.enter="add" placeholder="myshop.com"
                               @disabled(! $canAddMore)
                               class="w-full max-w-sm rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none disabled:bg-slate-50">
                        <button type="button" wire:click="add" @disabled(! $canAddMore) class="btn btn-primary disabled:opacity-50">
                            <span wire:loading.remove wire:target="add">Add domain</span>
                            <span wire:loading wire:target="add">Adding…</span>
                        </button>
                    </div>
                    @error('hostname') <p class="mt-1.5 text-sm text-rose-700">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-slate-500">Just the address — no https://. We add the www version for you.</p>
                </div>

                @if ($own->isEmpty())
                    <div class="flex flex-col items-center justify-center px-5 py-10 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                        </span>
                        <p class="mt-3 text-sm font-medium text-slate-600">No domain of your own yet</p>
                        <p class="mt-1 max-w-xs text-sm text-slate-500">
                            Add one above, then follow the steps on the right to point it here.
                        </p>
                    </div>
                @else
                    <ul class="divide-y divide-slate-50">
                        @foreach ($own as $domain)
                            @php($working = $domain->status === 'verified')
                            <li wire:key="domain-{{ $domain->id }}"
                                class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 {{ $justChanged === $domain->id ? 'settled' : '' }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                                                 {{ $working ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        @if ($working)
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7" /></svg>
                                        @else
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2" /></svg>
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="https://{{ $domain->hostname }}" target="_blank" rel="noopener"
                                               class="truncate font-medium hover:underline">{{ $domain->hostname }}</a>
                                            @if ($domain->is_primary)
                                                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">Main address</span>
                                            @endif
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium
                                                         {{ $working ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                                {{ $working ? 'Working' : 'Waiting for DNS' }}
                                            </span>
                                        </div>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            @if ($domain->last_checked_at)
                                                {{ $domain->last_check_result }} · looked {{ $domain->last_checked_at->diffForHumans() }}
                                            @else
                                                Not looked yet. Add the records, then press Check now.
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 gap-2">
                                    <button wire:click="checkNow({{ $domain->id }})" class="btn btn-quiet !px-3 !py-1.5">
                                        <span wire:loading.remove wire:target="checkNow({{ $domain->id }})">Check now</span>
                                        <span wire:loading wire:target="checkNow({{ $domain->id }})">Looking…</span>
                                    </button>
                                    @if (! $domain->is_primary && $working)
                                        <button wire:click="makePrimary({{ $domain->id }})" class="btn btn-quiet !px-3 !py-1.5">Make main</button>
                                    @endif
                                    <button wire:click="remove({{ $domain->id }})"
                                            wire:confirm="Remove {{ $domain->hostname }}? Your shop will stop answering on it."
                                            class="btn !px-3 !py-1.5 border border-rose-200 text-rose-700 hover:bg-rose-50">Remove</button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Right: how to do it ------------------------------------------- --}}
        <div class="rise rise-3">
            @include('livewire.admin.partials.domain-steps')
        </div>
    </div>
</div>
