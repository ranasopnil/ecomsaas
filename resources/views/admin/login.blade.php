<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — {{ $store->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex h-full items-center justify-center bg-slate-100 text-slate-900 antialiased">
    <div class="w-full max-w-sm">
        <h1 class="mb-1 text-xl font-semibold">{{ $store->name }}</h1>
        <p class="mb-6 text-sm text-slate-500">Sign in to manage your shop.</p>

        <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4 rounded-xl bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                <input id="password" name="password" type="password" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                Stay signed in
            </label>

            @if ($errors->any())
                <p class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">{{ $errors->first() }}</p>
            @endif

            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Sign in
            </button>
        </form>
    </div>
</body>
</html>
