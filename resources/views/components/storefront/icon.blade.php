@props(['name'])

@php
    // A page that asks for its own size gets it. Adding the default alongside
    // it would leave two sizes on one drawing, and the larger one would win
    // whatever the page asked for.
    $sized = preg_match('/(^|\s)(h|w|size)-/', (string) $attributes->get('class', '')) === 1;
@endphp

{{-- The small line drawings used across a shop front. One place, so they all match. --}}
<svg {{ $attributes->merge(['class' => $sized ? '' : 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none"
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

        @case('basket')
            <path d="M3 9h18l-1.5 10.5a1 1 0 0 1-1 .5h-13a1 1 0 0 1-1-.5L3 9Z"/>
            <path d="M8 9l2.5-5M16 9l-2.5-5M9 13v4M12 13v4M15 13v4"/>
            @break

        @case('check')
            <path d="m5 12.5 4.5 4.5L19 7.5"/>
            @break

        @case('chevron')
            <path d="m6 9 6 6 6-6"/>
            @break

        @case('tag')
            <path d="M3.5 12.5v-8a1 1 0 0 1 1-1h8l8 8-8 8-9-7Z"/>
            <circle cx="8" cy="8" r="1.2" fill="currentColor" stroke="none"/>
            @break

        @case('bike')
            <circle cx="6" cy="16" r="3.5"/>
            <circle cx="18" cy="16" r="3.5"/>
            <path d="M6 16l4-8h4l4 8M10 8h4l-2 8"/>
            @break

        @case('arrow')
            <path d="M5 12h14M13 6l6 6-6 6"/>
            @break

        @case('phone')
            <path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7 2 2 0 0 1 6.5 3.5Z"/>
            @break

        @case('mail')
            <rect x="3" y="5.5" width="18" height="13" rx="2.5"/>
            <path d="m3.8 7 8.2 5.5L20.2 7"/>
            @break

        @case('clock')
            <circle cx="12" cy="12" r="8.5"/>
            <path d="M12 7.5V12l3 1.8"/>
            @break

        @case('facebook')
            <path d="M13.6 21v-7.7h2.6l.4-3h-3v-1.9c0-.87.24-1.46 1.5-1.46H16.7V3.24C16.4 3.2 15.4 3.1 14.2 3.1c-2.4 0-4.1 1.47-4.1 4.18V10.3H7.5v3h2.6V21Z"
                  fill="currentColor" stroke="none"/>
            @break

        @case('instagram')
            <rect x="3.5" y="3.5" width="17" height="17" rx="5"/>
            <circle cx="12" cy="12" r="4"/>
            <circle cx="16.9" cy="7.1" r="1.1" fill="currentColor" stroke="none"/>
            @break

        @case('youtube')
            <rect x="2.5" y="5.5" width="19" height="13" rx="4"/>
            <path d="m10.2 9.3 5 2.7-5 2.7Z" fill="currentColor"/>
            @break

        @case('tiktok')
            <path d="M14.2 3.2v10.9a3.4 3.4 0 1 1-3-3.37"/>
            <path d="M14.2 3.2a5 5 0 0 0 5 4.6"/>
            @break

        @case('x')
            <path d="m4.5 4.5 15 15M19.5 4.5l-15 15"/>
            @break

        @case('wallet')
            <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18v3"/>
            <path d="M3 7.5V18a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3M3 7.5h16a2 2 0 0 1 2 2V15"/>
            <path d="M17 11.5h4v3.5h-4a1.75 1.75 0 0 1 0-3.5Z"/>
            @break

        @case('card')
            <rect x="3" y="5.5" width="18" height="13" rx="2.5"/>
            <path d="M3 10h18M6.5 14.5h3"/>
            @break

        @case('van')
            <path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/>
            <circle cx="7.5" cy="17.8" r="1.8"/>
            <circle cx="17.5" cy="17.8" r="1.8"/>
            @break

        @case('linkedin')
            <rect x="3.5" y="3.5" width="17" height="17" rx="3.5"/>
            <path d="M8 10.5V16M8 7.6v.02M12 16v-3.2a1.9 1.9 0 0 1 3.8 0V16"/>
            @break

        @case('whatsapp')
            <path d="M20.5 11.7a8.4 8.4 0 0 1-12.5 7.3L4 20.2l1.2-4A8.4 8.4 0 1 1 20.5 11.7Z"/>
            <path d="M9.3 8.6c.3-.05.6.04.75.35l.6 1.2a.8.8 0 0 1-.1.85l-.4.45a5.6 5.6 0 0 0 2.7 2.6l.45-.45a.8.8 0 0 1 .85-.1l1.2.55c.3.15.4.45.35.75a2 2 0 0 1-2.2 1.6 7.2 7.2 0 0 1-5.6-5.5 2 2 0 0 1 1.4-2.3Z"/>
            @break
    @endswitch
</svg>
