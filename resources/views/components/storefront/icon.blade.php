@props(['name'])

{{-- The small line drawings used across a shop front. One place, so they all match. --}}
<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true">
    @switch($name)
        @case('pin')
            <path d="M12 21s7-5.686 7-11a7 7 0 1 0-14 0c0 5.314 7 11 7 11Z"/>
            <circle cx="12" cy="10" r="2.5"/>
            @break

        @case('crosshair')
            <circle cx="12" cy="12" r="7"/>
            <circle cx="12" cy="12" r="2.5" fill="currentColor" stroke="none"/>
            <path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
            @break

        @case('bag')
            <path d="M6 7h12l-1 13H7L6 7Z"/>
            <path d="M9 7a3 3 0 0 1 6 0"/>
            @break

        @case('grid')
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/>
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/>
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/>
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>
            @break

        @case('people')
            <circle cx="9" cy="8" r="3"/>
            <path d="M3.5 19a5.5 5.5 0 0 1 11 0"/>
            <path d="M16 5.5a3 3 0 0 1 0 5.8M17 14.2a5.5 5.5 0 0 1 3.5 4.8"/>
            @break

        @case('search')
            <circle cx="11" cy="11" r="6"/>
            <path d="m20 20-3.5-3.5"/>
            @break
    @endswitch
</svg>
