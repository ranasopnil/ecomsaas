@php
    $steps = [
        ['title' => 'Sign in where you bought the domain',
         'body' => 'GoDaddy, Namecheap, Cloudflare, Exabytes — wherever you pay for it.'],
        ['title' => 'Open the DNS settings',
         'body' => 'It may be called DNS, Name servers, Advanced DNS or Manage DNS.'],
        ['title' => 'Add these two records',
         'body' => null, 'records' => true],
        ['title' => 'Save, then wait',
         'body' => 'Usually a few minutes, sometimes a few hours. We keep looking on our own, and you can press Check now.'],
        ['title' => 'Your shop answers on it',
         'body' => 'The padlock is set up for you automatically. Nothing to buy, nothing to install.'],
    ];
@endphp

<div class="card overflow-hidden">
    <div class="border-b border-slate-100 bg-gradient-to-br from-violet-50 to-white p-5">
        <h2 class="font-semibold">Pointing your domain here</h2>
        <p class="mt-1 text-sm text-slate-500">Five steps, done once.</p>
    </div>

    <ol class="p-5">
        @foreach ($steps as $index => $step)
            <li class="relative ps-11 {{ $loop->last ? '' : 'pb-6' }}">
                {{-- The thread and arrow joining this step to the next. --}}
                @unless ($loop->last)
                    <span class="absolute start-[0.9rem] top-9 bottom-1 w-px bg-gradient-to-b from-violet-200 to-violet-100"></span>
                    <svg class="absolute start-[0.55rem] bottom-0 h-3 w-3 text-violet-300" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                @endunless

                <span class="absolute start-0 top-0 flex h-8 w-8 items-center justify-center rounded-full
                             bg-gradient-to-br from-violet-600 to-indigo-700 text-sm font-bold text-white
                             shadow-[0_4px_10px_-2px_rgba(91,61,245,.5)]">
                    {{ $index + 1 }}
                </span>

                <p class="pt-1 text-sm font-semibold leading-snug">{{ $step['title'] }}</p>

                @if ($step['body'])
                    <p class="mt-1 text-sm text-slate-500">{{ $step['body'] }}</p>
                @endif

                @if ($step['records'] ?? false)
                    <div class="mt-3 space-y-2">
                        @foreach ([['@', 'your domain itself'], ['www', 'the www version']] as [$name, $what])
                            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                                    <span><span class="text-slate-400">Type</span>
                                        <code class="ms-1 rounded bg-white px-1.5 py-0.5 font-mono font-semibold">A</code></span>
                                    <span><span class="text-slate-400">Name</span>
                                        <code class="ms-1 rounded bg-white px-1.5 py-0.5 font-mono font-semibold">{{ $name }}</code></span>
                                    <span class="text-slate-400">{{ $what }}</span>
                                </div>

                                <div x-data="copyable(@js($serverIp))" class="mt-2 flex items-center gap-2">
                                    <span class="text-xs text-slate-400">Points to</span>
                                    <code class="flex-1 rounded bg-white px-2 py-1 font-mono text-xs font-semibold">{{ $serverIp }}</code>
                                    <button type="button" @click="copy()"
                                            class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs transition hover:bg-slate-50">
                                        <span x-show="! copied">Copy</span>
                                        <span x-show="copied" x-cloak class="text-emerald-700">Copied</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
</div>

{{-- The mistakes that cost people an afternoon. --}}
<div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
    <div class="flex items-center gap-2">
        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-200 text-amber-900">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <path d="M12 8v5M12 17h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
            </svg>
        </span>
        <h3 class="font-semibold text-amber-900">What not to do</h3>
    </div>

    <ul class="mt-3 space-y-2.5 text-sm text-amber-900">
        @foreach ([
            ['On Cloudflare, leave the orange cloud on.', 'Switch each record to DNS only — the grey cloud. With the orange cloud your address never reaches us and the padlock cannot be set up.'],
            ['Use a CNAME for the domain itself.', 'The plain domain needs an A record. A CNAME there breaks email and is rejected by many providers.'],
            ['Use forwarding or redirect.', 'Web forwarding sends visitors somewhere else instead of pointing the address at us. Your shop will not load.'],
            ['Type https:// in the Name box.', 'The Name is only @ or www. Nothing else goes in it.'],
            ['Change your nameservers to us.', 'We do not run your DNS. Leave your nameservers exactly as they are.'],
        ] as [$dont, $why])
            <li class="flex gap-2.5">
                <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-amber-300/70 text-[0.6rem] font-bold text-amber-900">✕</span>
                <span>
                    <span class="font-semibold">{{ $dont }}</span>
                    <span class="block text-amber-800/90">{{ $why }}</span>
                </span>
            </li>
        @endforeach
    </ul>
</div>
