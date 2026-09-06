@php
    $variants = $product->variants;
    $first = $product->defaultVariant();
    $mainImage = $product->primaryImage();
    $tracked = $first?->inventory?->track_inventory ?? true;
    $available = (int) ($first?->inventory?->available ?? 0);
    $rtl = in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $product->meta_title ?: $product->name }} — {{ $store->name }}</title>
    @if ($product->meta_description)
        <meta name="description" content="{{ $product->meta_description }}">
    @endif
    @if ($isPreview)
        <meta name="robots" content="noindex">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white text-slate-900 antialiased">
    @if ($isPreview)
        <div class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900">
            You are looking at this as a customer would. It is not on sale yet, so nobody else can see it.
            <a href="{{ route('admin.products.edit', $product) }}" class="underline">Back to editing</a>
        </div>
    @endif

    <header class="border-b border-slate-200">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="/" class="text-lg font-semibold">{{ $store->name }}</a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10">
        <div class="grid gap-10 md:grid-cols-2">
            <div x-data="{ shown: @js($mainImage?->url()) }">
                <div class="aspect-square overflow-hidden rounded-xl bg-slate-100">
                    @if ($mainImage)
                        <img :src="shown" src="{{ $mainImage->url() }}" alt="{{ $mainImage->alt_text ?: $product->name }}"
                             class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-sm text-slate-400">
                            No photo yet
                        </div>
                    @endif
                </div>

                @if ($product->images->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @foreach ($product->images as $image)
                            <button type="button" @click="shown = @js($image->url())"
                                    class="aspect-square overflow-hidden rounded-lg border border-slate-200">
                                <img src="{{ $image->thumbnailUrl() }}" alt="{{ $image->alt_text ?: $product->name }}"
                                     class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                @if ($product->brand)
                    <p class="text-sm text-slate-500">{{ $product->brand->name }}</p>
                @endif

                <h1 class="mt-1 text-3xl font-semibold">{{ $product->name }}</h1>

                @if ($first)
                    <div class="mt-4 flex items-baseline gap-3">
                        <span class="text-2xl font-semibold tabular-nums">
                            {{ $first->currency }} {{ $first->price->toDecimal() }}
                        </span>
                        @if ($first->compare_at_price_minor && $first->compare_at_price_minor > $first->price_minor)
                            <span class="text-lg text-slate-400 line-through tabular-nums">
                                {{ $first->currency }} {{ number_format($first->compare_at_price_minor / (10 ** $first->currency_exponent), $first->currency_exponent) }}
                            </span>
                        @endif
                    </div>
                @endif

                @if ($product->has_variants)
                    <div class="mt-6 space-y-4">
                        @foreach ($product->options as $option)
                            <div>
                                <p class="mb-2 text-sm font-medium">{{ $option->name }}</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($option->values as $value)
                                        <span class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">{{ $value->value }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <p class="mt-6 text-sm">
                    @if (! $tracked)
                        <span class="text-emerald-700">Available to order</span>
                    @elseif ($available > 0)
                        <span class="text-emerald-700">In stock</span>
                    @else
                        <span class="text-rose-700">Sold out</span>
                    @endif
                </p>

                <button type="button" disabled
                        class="mt-6 w-full cursor-not-allowed rounded-lg bg-slate-300 px-5 py-3 text-sm font-medium text-slate-600">
                    Add to basket
                </button>
                <p class="mt-2 text-xs text-slate-500">The basket and checkout are being built next.</p>

                @if ($product->description)
                    <div class="shop-description mt-8 text-sm">
                        {!! $product->description !!}
                    </div>
                @endif
            </div>
        </div>
    </main>

    <footer class="border-t border-slate-200 py-8 text-center text-sm text-slate-500">
        {{ $store->name }}
    </footer>
</body>
</html>
