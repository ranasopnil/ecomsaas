{{--
    A product box before it arrives.

    Deliberately the same shape as the real one in
    components/storefront/product-card.blade.php: square photo, two lines of
    name, a price, a reduction tag. If that card changes, change this too, or
    the page will jump when the real thing lands.
--}}
<div class="flex flex-col">
    <div class="aspect-square rounded-2xl bg-slate-200/70 ring-1 ring-slate-100"></div>

    <div class="mt-2.5 flex flex-col gap-1.5">
        <x-skeleton.bar w="w-11/12" h="h-3" />
        <x-skeleton.bar w="w-2/3" h="h-3" />
        <x-skeleton.bar w="w-16" h="h-4" class="mt-1" />
        <x-skeleton.bar w="w-10" h="h-3.5" round="rounded-md" />
    </div>
</div>
