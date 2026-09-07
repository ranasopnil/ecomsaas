<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Super admin' }} — Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-full">
        <header class="bg-slate-900 text-slate-100">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('super.dashboard') }}" wire:navigate class="text-sm font-semibold tracking-wide">
                        Platform staff
                    </a>
                    <nav class="flex items-center gap-4 text-sm">
                        @foreach ([
                            'super.dashboard' => 'Overview',
                            'super.packages.index' => 'Plans',
                            'super.stores.index' => 'Shops',
                            'super.gateways.index' => 'Gateways',
                            'super.templates.index' => 'Templates',
                            'super.maps.index' => 'Maps',
                        ] as $route => $label)
                            <a href="{{ route($route) }}" wire:navigate
                               class="rounded px-2 py-1 {{ request()->routeIs($route) ? 'bg-slate-700 text-white' : 'text-slate-300 hover:text-white' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </div>
                <form method="POST" action="{{ route('super.logout') }}" class="flex items-center gap-3 text-sm">
                    @csrf
                    <span class="text-slate-400">{{ auth('admin')->user()?->name }}</span>
                    <button type="submit" class="rounded bg-slate-700 px-3 py-1 hover:bg-slate-600">Sign out</button>
                </form>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8" data-page-body>
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    {{ session('error') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
    @livewireScripts
    <x-skeleton.shapes />
</body>
</html>
