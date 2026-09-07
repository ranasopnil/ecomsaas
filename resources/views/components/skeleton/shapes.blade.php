@props(['accent' => '#16a34a'])

{{--
    Every shape a page can wear while it is on its way.

    These are <template> tags: the browser does not draw them, so the cost of
    carrying all of them is a few kilobytes of markup. resources/js/skeleton.js
    picks one by the address being opened and drops it into the page body, so
    the shopper sees the shape of what is coming rather than a blank screen.

    A new page needs nothing done to it: unnamed addresses get the plain shape
    at the bottom.
--}}

{{-- The shop front --}}
<template data-skeleton="home">
    <div class="animate-pulse">
        <section class="bg-slate-50 pb-10 pt-6">
            <div class="mx-auto max-w-6xl px-4">
                <div class="rounded-3xl bg-slate-100 px-5 py-8 sm:px-10 sm:py-10">
                    <div class="mx-auto flex max-w-3xl flex-col items-center gap-2.5">
                        <x-skeleton.bar w="w-4/5" h="h-7" round="rounded-lg" />
                        <x-skeleton.bar w="w-2/5" h="h-7" round="rounded-lg" />
                        <x-skeleton.bar w="w-2/3" h="h-3" class="mt-2" />
                        <x-skeleton.bar w="w-1/3" h="h-3" />
                        <div class="mt-3 h-11 w-full max-w-2xl rounded-xl bg-white ring-1 ring-slate-100"></div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 ring-1 ring-slate-100">
                            <div class="h-9 w-9 shrink-0 rounded-lg bg-slate-200/70"></div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <x-skeleton.bar w="w-12" h="h-4" />
                                <x-skeleton.bar w="w-20" h="h-2.5" />
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 py-8 sm:py-10">
            <div class="mb-8 h-12 w-full rounded-xl bg-white ring-1 ring-slate-100"></div>
            <x-skeleton.bar w="w-40" h="h-5" class="mb-4" />
            <div class="mb-10 flex gap-4 overflow-hidden">
                @for ($i = 0; $i < 8; $i++)
                    <div class="flex w-24 shrink-0 flex-col items-center gap-2">
                        <div class="h-20 w-20 rounded-full bg-slate-200/70"></div>
                        <x-skeleton.bar w="w-14" h="h-2.5" />
                    </div>
                @endfor
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @for ($i = 0; $i < 10; $i++)
                    <x-skeleton.product-card />
                @endfor
            </div>
        </div>
    </div>
</template>

{{-- Walking round the shop --}}
<template data-skeleton="browse">
    <div class="mx-auto max-w-7xl animate-pulse px-4 py-6 lg:flex lg:gap-6">
        <aside class="mb-6 lg:mb-0 lg:w-64 lg:shrink-0">
            <div class="rounded-2xl bg-white p-3 ring-1 ring-slate-100">
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex items-center gap-3 px-3 py-2.5">
                        <div class="h-5 w-5 shrink-0 rounded bg-slate-200/70"></div>
                        <x-skeleton.bar w="w-24" h="h-3" />
                    </div>
                @endfor
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <x-skeleton.bar w="w-20" h="h-4" class="mx-3 mb-3" />
                    @for ($i = 0; $i < 8; $i++)
                        <div class="border-t border-slate-100 px-3 py-2.5 first:border-0">
                            <x-skeleton.bar w="w-28" h="h-3" />
                        </div>
                    @endfor
                </div>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <section class="rounded-2xl bg-white px-5 py-10 sm:px-10">
                <div class="mx-auto flex max-w-3xl flex-col items-center gap-3">
                    <x-skeleton.bar w="w-2/3" h="h-7" round="rounded-lg" />
                    <x-skeleton.bar w="w-1/2" h="h-3" />
                    <div class="mt-4 h-12 w-full rounded-full bg-slate-100"></div>
                </div>
            </section>

            <div class="mt-8">
                <x-skeleton.bar w="w-48" h="h-5" class="mb-4" />
                <div class="flex gap-5 overflow-hidden">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="flex w-28 shrink-0 flex-col items-center gap-2">
                            <div class="h-24 w-24 rounded-full bg-slate-200/70"></div>
                            <x-skeleton.bar w="w-16" h="h-3" />
                        </div>
                    @endfor
                </div>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @for ($i = 0; $i < 15; $i++)
                    <x-skeleton.product-card />
                @endfor
            </div>
        </main>
    </div>
</template>

{{-- One product --}}
<template data-skeleton="product">
    <div class="mx-auto max-w-5xl animate-pulse px-4 py-10">
        <div class="grid gap-10 md:grid-cols-2">
            <div>
                <div class="aspect-square rounded-xl bg-slate-200/70"></div>
                <div class="mt-3 grid grid-cols-5 gap-2">
                    @for ($i = 0; $i < 5; $i++)
                        <div class="aspect-square rounded-lg bg-slate-200/60"></div>
                    @endfor
                </div>
            </div>

            <div class="space-y-4">
                <x-skeleton.bar w="w-24" h="h-3" />
                <x-skeleton.bar w="w-4/5" h="h-8" round="rounded-lg" />
                <x-skeleton.bar w="w-2/3" h="h-3" />
                <div class="flex items-center gap-3 pt-2">
                    <x-skeleton.bar w="w-32" h="h-7" round="rounded-lg" />
                    <x-skeleton.bar w="w-20" h="h-5" />
                </div>
                <x-skeleton.bar w="w-28" h="h-3" />
                <div class="flex gap-3 pt-4">
                    <div class="h-12 w-20 rounded-lg bg-slate-200/70"></div>
                    <div class="h-12 flex-1 rounded-lg bg-slate-300/70"></div>
                </div>
                <div class="space-y-2 pt-6">
                    <x-skeleton.bar w="w-full" h="h-3" />
                    <x-skeleton.bar w="w-11/12" h="h-3" />
                    <x-skeleton.bar w="w-3/4" h="h-3" />
                </div>
            </div>
        </div>
    </div>
</template>

{{-- The basket --}}
<template data-skeleton="basket">
    <div class="mx-auto max-w-5xl animate-pulse px-4 py-8">
        <x-skeleton.bar w="w-40" h="h-6" round="rounded-lg" class="mb-6" />
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-slate-100">
                        <div class="h-20 w-20 shrink-0 rounded-xl bg-slate-200/70"></div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <x-skeleton.bar w="w-2/3" h="h-3.5" />
                            <x-skeleton.bar w="w-24" h="h-2.5" />
                            <div class="h-8 w-28 rounded-full bg-slate-100"></div>
                        </div>
                        <x-skeleton.bar w="w-16" h="h-4" />
                    </div>
                @endfor
            </div>
            <div class="h-fit space-y-3 rounded-2xl bg-white p-5 ring-1 ring-slate-100">
                <x-skeleton.bar w="w-24" h="h-4" />
                <x-skeleton.bar w="w-full" h="h-3" />
                <x-skeleton.bar w="w-full" h="h-3" />
                <div class="h-12 w-full rounded-xl bg-slate-200/70"></div>
            </div>
        </div>
    </div>
</template>

{{-- Just the shelf, for when only the category changed --}}
<template data-skeleton="results">
    <div class="animate-pulse pt-8">
        <div class="mb-4 flex items-end justify-between gap-3">
            <x-skeleton.bar w="w-40" h="h-5" round="rounded-lg" />
            <x-skeleton.bar w="w-16" h="h-3" />
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @for ($i = 0; $i < 10; $i++)
                <x-skeleton.product-card />
            @endfor
        </div>
    </div>
</template>

{{-- A list behind the counter --}}
<template data-skeleton="admin-list">
    <div class="animate-pulse space-y-6">
        <div class="flex items-center justify-between gap-4">
            <x-skeleton.bar w="w-48" h="h-6" round="rounded-lg" />
            <div class="h-10 w-32 rounded-xl bg-slate-200/70"></div>
        </div>
        <div class="rounded-2xl bg-white p-4 ring-1 ring-slate-100">
            <div class="h-10 w-full rounded-xl bg-slate-100"></div>
            @for ($i = 0; $i < 8; $i++)
                <div class="flex items-center gap-4 border-t border-slate-100 py-4">
                    <div class="h-10 w-10 shrink-0 rounded-lg bg-slate-200/70"></div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <x-skeleton.bar w="w-1/3" h="h-3" />
                        <x-skeleton.bar w="w-1/4" h="h-2.5" />
                    </div>
                    <x-skeleton.bar w="w-16" h="h-3" />
                    <x-skeleton.bar w="w-10" h="h-3" />
                </div>
            @endfor
        </div>
    </div>
</template>

{{-- A form behind the counter --}}
<template data-skeleton="admin-form">
    <div class="animate-pulse space-y-6">
        <x-skeleton.bar w="w-56" h="h-6" round="rounded-lg" />
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                @for ($i = 0; $i < 4; $i++)
                    <div class="space-y-2 rounded-2xl bg-white p-5 ring-1 ring-slate-100">
                        <x-skeleton.bar w="w-28" h="h-3" />
                        <div class="h-11 w-full rounded-xl bg-slate-100"></div>
                        <x-skeleton.bar w="w-2/3" h="h-2.5" />
                    </div>
                @endfor
            </div>
            <div class="space-y-4">
                @for ($i = 0; $i < 2; $i++)
                    <div class="space-y-3 rounded-2xl bg-white p-5 ring-1 ring-slate-100">
                        <x-skeleton.bar w="w-24" h="h-3" />
                        <div class="h-24 w-full rounded-xl bg-slate-100"></div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
</template>

{{-- Anything not named above --}}
<template data-skeleton="generic">
    <div class="mx-auto max-w-5xl animate-pulse px-4 py-10">
        <x-skeleton.bar w="w-1/3" h="h-7" round="rounded-lg" class="mb-6" />
        <div class="space-y-4">
            @for ($i = 0; $i < 4; $i++)
                <div class="space-y-3 rounded-2xl bg-white p-5 ring-1 ring-slate-100">
                    <x-skeleton.bar w="w-1/4" h="h-4" />
                    <x-skeleton.bar w="w-full" h="h-3" />
                    <x-skeleton.bar w="w-5/6" h="h-3" />
                </div>
            @endfor
        </div>
    </div>
</template>
