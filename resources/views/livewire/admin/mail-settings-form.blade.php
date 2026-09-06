<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Email</h1>
        <p class="mt-1 text-sm text-slate-500">
            Send your shop's emails from your own address, through your own email provider.
        </p>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">

            {{-- Where email goes out from right now --}}
            <div class="card rise rise-1 p-5">
                @php($live = $settings?->isLive())
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white
                                     {{ $live ? 'bg-gradient-to-br from-emerald-500 to-teal-700' : 'bg-gradient-to-br from-slate-500 to-slate-700' }}">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18v12H3zM3 7l9 6 9-6" />
                            </svg>
                        </span>
                        <div>
                            <p class="font-semibold">
                                @if ($live)
                                    Emails go out from {{ $settings->from_address }}
                                @else
                                    Emails go out from the platform
                                @endif
                            </p>
                            <p class="mt-0.5 text-sm text-slate-500">
                                @if ($live)
                                    Through your own server, {{ $settings->host }}.
                                @elseif ($settings && $settings->is_enabled)
                                    Yours is switched on but not yet proven. Send a test email below.
                                @elseif ($settings)
                                    Yours is saved but switched off. Turn it on below once the test passes.
                                @else
                                    Set up your own below so customers see your address, not ours.
                                @endif
                            </p>
                        </div>
                    </div>

                    @if ($settings)
                        @php($tone = match ($settings->status) {
                            'working' => 'bg-emerald-50 text-emerald-700',
                            'failing' => 'bg-rose-50 text-rose-700',
                            default => 'bg-amber-50 text-amber-700',
                        })
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $tone }}">
                            {{ ['working' => 'Test passed', 'failing' => 'Test failed', 'untested' => 'Not tested yet'][$settings->status] }}
                        </span>
                    @endif
                </div>

                @if ($settings?->last_test_result)
                    <p class="mt-4 rounded-xl px-4 py-3 text-sm
                              {{ $settings->status === 'working' ? 'bg-emerald-50 text-emerald-900' : 'bg-rose-50 text-rose-900' }}">
                        {{ $settings->last_test_result }}
                        <span class="text-xs opacity-70">· {{ $settings->last_tested_at?->diffForHumans() }}</span>
                    </p>
                @endif
            </div>

            {{-- The settings --}}
            <form wire:submit="save" class="card rise rise-2 p-6">
                <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-400">Your email server</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Mail server (SMTP host)</label>
                        <input type="text" wire:model="host" placeholder="smtp.gmail.com"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        @error('host') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Port</label>
                        <input type="number" wire:model="port" min="1" max="65535"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        @error('port') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Security</label>
                        <select wire:model="encryption"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                            <option value="tls">TLS (usually port 587)</option>
                            <option value="ssl">SSL (usually port 465)</option>
                            <option value="none">None (not recommended)</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Username</label>
                        <input type="text" wire:model="username" placeholder="you@yourshop.com" autocomplete="off"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        @error('username') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Password</label>
                        <input type="password" wire:model="password" autocomplete="new-password"
                               placeholder="{{ $hasPassword ? '•••••••• saved — type to replace' : '' }}"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">
                            @if ($hasPassword)
                                Stored securely. It is never shown again; leave this empty to keep it.
                            @else
                                Stored securely and never shown again.
                            @endif
                        </p>
                        @error('password') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">From address</label>
                        <input type="email" wire:model="from_address" placeholder="orders@yourshop.com"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        <p class="mt-1 text-xs text-slate-500">What customers see the email is from. Usually has to match the account.</p>
                        @error('from_address') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">From name</label>
                        <input type="text" wire:model="from_name"
                               class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                        @error('from_name') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm sm:col-span-2">
                        <input type="checkbox" wire:model="is_enabled" class="rounded border-slate-300">
                        Use this for my shop's emails
                        <span class="text-xs text-slate-500">— only takes effect once the test has passed</span>
                    </label>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn btn-primary">
                        <span wire:loading.remove wire:target="save">Save settings</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>

                    @if ($settings)
                        <button type="button" wire:click="forget"
                                wire:confirm="Remove your email settings? Email will go through the platform again."
                                class="btn !px-3 border border-rose-200 text-rose-700 hover:bg-rose-50">Remove</button>
                    @endif
                </div>
            </form>

            {{-- Prove it --}}
            <div class="card rise rise-3 p-6">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Send a test email</h2>
                <p class="mt-1 mb-4 text-sm text-slate-500">
                    Sends one real email through the settings above, so you know they work before a customer needs them.
                </p>

                <div class="flex flex-wrap gap-2">
                    <input type="email" wire:model="test_to" wire:keydown.enter="sendTest" placeholder="you@example.com"
                           class="w-full max-w-sm rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-violet-400 focus:outline-none">
                    <button type="button" wire:click="sendTest" @disabled(! $settings) class="btn btn-quiet disabled:opacity-50">
                        <span wire:loading.remove wire:target="sendTest">Send test</span>
                        <span wire:loading wire:target="sendTest">Sending…</span>
                    </button>
                </div>
                @error('test_to') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                @unless ($settings)
                    <p class="mt-2 text-xs text-slate-500">Save your settings first.</p>
                @endunless
            </div>
        </div>

        {{-- Right: the settings most people need --}}
        <div class="rise rise-3 space-y-4">
            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 bg-gradient-to-br from-violet-50 to-white p-5">
                    <h2 class="font-semibold">Common providers</h2>
                    <p class="mt-1 text-sm text-slate-500">The settings each one expects.</p>
                </div>
                <ul class="divide-y divide-slate-50 text-sm">
                    @foreach ([
                        ['Gmail / Google Workspace', 'smtp.gmail.com', '587', 'TLS', 'Needs an App Password, made in your Google account under Security. Your normal password will not work.'],
                        ['Outlook / Microsoft 365', 'smtp.office365.com', '587', 'TLS', 'SMTP sending has to be switched on for the mailbox by whoever runs your Microsoft account.'],
                        ['Zoho Mail', 'smtp.zoho.com', '587', 'TLS', 'Sign in with your full email address as the username.'],
                        ['Your hosting company', 'mail.yourdomain.com', '587 or 465', 'TLS or SSL', 'Ask them for the outgoing (SMTP) server. It is usually in the same place as your email account details.'],
                    ] as [$name, $host, $port, $enc, $note])
                        <li class="p-4">
                            <p class="font-semibold">{{ $name }}</p>
                            <p class="mt-1 font-mono text-xs text-slate-600">{{ $host }} · port {{ $port }} · {{ $enc }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $note }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-semibold">Good to know</p>
                <ul class="mt-2 space-y-2 text-amber-900/90">
                    <li>Your password is encrypted and never shown again, not even to you.</li>
                    <li>Emails only switch to your server once a test email has gone through. Until then they keep going out from the platform, so nothing gets lost.</li>
                    <li>If your provider stops working later, fix it here and send another test.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
