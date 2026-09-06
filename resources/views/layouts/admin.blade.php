@php($store = App\Facades\Tenancy::current())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin' }} — {{ $store?->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-full">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="text-sm font-semibold">{{ $store?->name }}</a>
                    <nav class="flex items-center gap-1 text-sm">
                        @foreach ([
                            'admin.dashboard' => 'Dashboard',
                            'admin.products.index' => 'Products',
                            'admin.stock.index' => 'Stock',
                            'admin.categories.index' => 'Categories',
                            'admin.brands.index' => 'Brands',
                        ] as $route => $label)
                            <a href="{{ route($route) }}" wire:navigate
                               class="rounded px-3 py-1.5 {{ request()->routeIs($route) ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}" class="flex items-center gap-3 text-sm">
                    @csrf
                    <span class="text-slate-500">{{ auth()->user()?->name }}</span>
                    <button type="submit" class="rounded border border-slate-300 px-3 py-1 hover:bg-slate-50">Sign out</button>
                </form>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
    @livewireScripts
</body>
</html>
