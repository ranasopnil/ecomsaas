<div class="space-y-6">
    <div class="rise">
        <h1 class="text-3xl font-bold tracking-tight">Payments</h1>
        <p class="mt-1 text-sm text-slate-500">
            How customers pay you. Each one uses your own account — you enter your own details, and the money goes to you.
        </p>
    </div>

    @if ($available->isEmpty())
        <div class="card rise rise-1 p-8 text-center text-sm text-slate-500">
            No ways of taking money are available to your shop yet. Ask the platform.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($available as $key => $gateway)
            @php($method = $methods->get($key))
            @php($on = $method?->isReady())
            <div wire:key="pm-{{ $key }}" class="card card-hover p-5 {{ $justChanged === $key ? 'settled' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <x-admin.gateway-logo :gateway="$key" class="mt-0.5" />

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ $method?->name() ?? $gateway['name'] }}</h2>
                            @if ($on)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">On</span>
                            @elseif ($method)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                                    {{ $method->is_enabled ? 'Details missing' : 'Off' }}
                                </span>
                            @endif
                            @if ($gateway['reason'] === 'granted')
                                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-800">Given to you</span>
                            @endif
                            @unless ($gateway['covered_by_plan'])
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Not in your plan</span>
                            @endunless
                        </div>
                            <p class="mt-1 text-sm text-slate-500">{{ $gateway['blurb'] }}</p>
                        </div>
                    </div>

                    @if ($method)
                        <button type="button" wire:click="toggle('{{ $key }}')" title="{{ $method->is_enabled ? 'Switch off' : 'Switch on' }}"
                                class="inline-flex h-7 w-12 shrink-0 items-center rounded-full p-0.5 transition
                                       {{ $method->is_enabled ? 'justify-end bg-emerald-500' : 'justify-start bg-slate-300' }}">
                            <span class="h-6 w-6 rounded-full bg-white shadow"></span>
                        </button>
                    @endif
                </div>

                @if ($method && $editing !== $key)
                    <dl class="mt-4 space-y-1 text-sm">
                        @foreach ($gateway['fields'] as $fieldKey => $field)
                            @php($shown = $field['secret']
                                ? $method->maskedSecret($fieldKey)
                                : (($field['type'] ?? 'text') === 'checkbox'
                                    ? (($method->settings[$fieldKey] ?? false) ? 'Yes' : 'No')
                                    : ($field['type'] === 'select'
                                        ? ($field['options'][$method->settings[$fieldKey] ?? ''] ?? null)
                                        : ($method->settings[$fieldKey] ?? null))))
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">{{ $field['label'] }}</dt>
                                <dd class="{{ $shown ? 'font-mono text-xs' : 'text-rose-600' }}">{{ $shown ?? 'Not set' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($editing === $key && $definition)
                    <form wire:submit="save" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">What customers see it called (optional)</label>
                            <input type="text" wire:model="display_name" placeholder="{{ $gateway['name'] }}"
                                   class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">
                        </div>

                        @foreach ($definition['fields'] as $fieldKey => $field)
                            <div>
                                @if (($field['type'] ?? 'text') === 'checkbox')
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" wire:model="form.{{ $fieldKey }}" class="rounded border-slate-300">
                                        {{ $field['label'] }}
                                    </label>
                                @else
                                    <label class="mb-1 block text-xs font-medium text-slate-500">{{ $field['label'] }}</label>
                                    @if ($field['type'] === 'select')
                                        <select wire:model="form.{{ $fieldKey }}"
                                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">
                                            <option value="">Choose…</option>
                                            @foreach ($field['options'] as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field['type'] === 'textarea')
                                        <textarea wire:model="form.{{ $fieldKey }}" rows="3" autocomplete="off"
                                                  placeholder="{{ $field['secret'] && $method?->hasSecret($fieldKey) ? 'Saved — paste to replace' : '' }}"
                                                  class="w-full rounded-xl border border-slate-200 px-3 py-2 font-mono text-xs focus:border-violet-400 focus:outline-none"></textarea>
                                    @else
                                        <input type="{{ $field['type'] === 'password' ? 'password' : 'text' }}"
                                               wire:model="form.{{ $fieldKey }}" autocomplete="{{ $field['secret'] ? 'new-password' : 'off' }}"
                                               placeholder="{{ $field['secret'] && $method?->hasSecret($fieldKey) ? '•••••••• saved — type to replace' : '' }}"
                                               class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-violet-400 focus:outline-none">
                                    @endif
                                    @if ($field['hint'] ?? false)
                                        <p class="mt-1 text-xs text-slate-500">{{ $field['hint'] }}</p>
                                    @elseif ($field['secret'])
                                        <p class="mt-1 text-xs text-slate-500">Stored encrypted and never shown again. Leave empty to keep what is saved.</p>
                                    @endif
                                @endif
                            </div>
                        @endforeach

                        {{-- Gateways that sign their own messages need to be told where to send them --}}
                        @php($webhookRoute = 'payments.'.$key.'.webhook')
                        @if (\Illuminate\Support\Facades\Route::has($webhookRoute))
                            <div class="rounded-xl bg-slate-50 p-3">
                                <p class="text-xs font-medium text-slate-600">
                                    Add this address as a webhook in {{ $gateway['name'] }}
                                </p>
                                <p class="mt-1 break-all font-mono text-xs text-slate-800">{{ route($webhookRoute) }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Then paste the signing secret it gives you into the box above. It is how your shop
                                    knows a message really came from {{ $gateway['name'] }}.
                                </p>
                            </div>
                        @endif

                        <div class="flex items-center gap-2 pt-1">
                            <button type="submit" class="btn btn-primary !px-4 !py-2">Save</button>
                            <button type="button" wire:click="cancel" class="text-sm text-slate-500 hover:text-slate-900">Cancel</button>
                        </div>
                    </form>
                @else
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="edit('{{ $key }}')" class="btn btn-quiet !px-3 !py-1.5">
                            {{ $method ? 'Change details' : ($gateway['fields'] === [] ? 'Set up' : 'Enter your details') }}
                        </button>

                        @if ($method && $factory->isDriven($key))
                            <button type="button" wire:click="test('{{ $key }}')"
                                    wire:loading.attr="disabled" wire:target="test('{{ $key }}')"
                                    class="btn btn-quiet !px-3 !py-1.5">
                                <span wire:loading.remove wire:target="test('{{ $key }}')">Test connection</span>
                                <span wire:loading wire:target="test('{{ $key }}')">Checking…</span>
                            </button>
                            @if ($testing->get($key))
                                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs text-sky-800">Test system</span>
                            @endif
                        @endif
                    </div>

                    @if ($method && $factory->isDriven($key))
                        <p class="mt-2 text-xs text-slate-500">
                            Testing asks {{ $gateway['name'] }} whether your details work. It moves no money.
                        </p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</div>
