@php
    $accent = $template['accent'];
@endphp

<x-layouts.storefront :store="$store" :location="$location" :search-url="$searchUrl" :accent="$accent"
                      :title="$page['title'].' — '.$store->name"
                      :description="$page['title'].' at '.$store->name.'.'">

    <main class="mx-auto max-w-3xl px-4 py-10">
        <nav class="mb-6 text-xs text-slate-500">
            <a href="{{ url('/') }}" class="hover:text-slate-900">{{ $store->name }}</a>
            <span class="mx-1">›</span>
            <span class="text-slate-700">{{ $page['title'] }}</span>
        </nav>

        <article class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100 sm:p-10">
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $page['title'] }}</h1>

            @if ($updatedAt)
                <p class="mt-2 text-xs text-slate-400">Last updated {{ $updatedAt->format('j F Y') }}</p>
            @endif

            <div class="mt-6 h-1 w-16 rounded-full" style="background: {{ $accent }}"></div>

            {{-- Exactly what the shopkeeper typed, shown as words and nothing else --}}
            <div class="mt-6 whitespace-pre-line text-[15px] leading-relaxed text-slate-700">{{ $page['body'] }}</div>
        </article>

        @if ($others->isNotEmpty())
            <div class="mt-8">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Also worth reading</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($others as $other)
                        <a href="{{ route('storefront.page', $other['slug']) }}"
                           class="rounded-full bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-100 transition hover:ring-slate-300">
                            {{ $other['title'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </main>
</x-layouts.storefront>
