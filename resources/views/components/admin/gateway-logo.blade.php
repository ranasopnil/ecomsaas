@props(['gateway', 'size' => 'md'])

@php
    /*
     * The mark for each way of taking money, so a shopkeeper finds the one
     * they want by its colour before they have read a word.
     *
     * Drawn here rather than fetched: nothing on this screen should depend on
     * a payment company's servers being up, and no shop's dashboard should
     * announce itself to them on every page load.
     */
    $marks = [
        'cod' => ['colour' => '#0F9D58', 'icon' => 'cash'],
        'self_mfs' => ['colour' => '#0EA5E9', 'icon' => 'phone'],
        'bkash' => ['colour' => '#E2136E', 'text' => 'b'],
        'nagad' => ['colour' => '#EE7623', 'text' => 'N'],
        'amarpay' => ['colour' => '#C8102E', 'text' => 'aP'],
        'sslcommerz' => ['colour' => '#1B3E70', 'text' => 'SSL'],
        'stripe' => ['colour' => '#635BFF', 'text' => 'S'],
        'airwallex' => ['colour' => '#E5382B', 'text' => 'A'],
        'platform' => ['colour' => '#5B3DF5', 'icon' => 'shop'],
    ];

    $mark = $marks[$gateway] ?? ['colour' => '#475569', 'text' => mb_strtoupper(mb_substr($gateway, 0, 1))];
    $text = $mark['text'] ?? null;

    $textSize = match (mb_strlen((string) $text)) {
        1 => 'text-xl',
        2 => 'text-base',
        default => 'text-[11px] tracking-tight',
    };

    // Written out in full rather than built from the prop: the stylesheet is
    // made by reading these files, and a class name stitched together at run
    // time is never seen by the thing that builds it.
    $box = $size === 'sm' ? 'h-9 w-9' : 'h-11 w-11';
@endphp

<span {{ $attributes->merge(['class' => "flex {$box} shrink-0 items-center justify-center rounded-xl font-bold text-white shadow-sm"]) }}
      style="background: {{ $mark['colour'] }}" aria-hidden="true">
    @if ($text !== null)
        <span class="{{ $textSize }} leading-none">{{ $text }}</span>
    @elseif (($mark['icon'] ?? null) === 'cash')
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
            <rect x="2.5" y="6" width="19" height="12" rx="2.5" />
            <circle cx="12" cy="12" r="2.6" />
            <path d="M6 9.5h.01M18 14.5h.01" />
        </svg>
    @elseif (($mark['icon'] ?? null) === 'phone')
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
            <rect x="6" y="2.5" width="12" height="19" rx="2.5" />
            <path d="M10.5 18.5h3" />
            <path d="M12 7v6M10 8.6h3a1.4 1.4 0 0 1 0 2.8h-2a1.4 1.4 0 0 0 0 2.8h3" />
        </svg>
    @else
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 9.5 5.5 4h13L20 9.5M4 9.5h16M4 9.5v10h16v-10" />
            <path d="M9.5 19.5v-5h5v5" />
        </svg>
    @endif
</span>
