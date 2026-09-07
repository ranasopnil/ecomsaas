<?php

/*
 * The shop fronts a merchant can choose between.
 *
 * These are first-party: each one is a set of Blade components in this
 * repository. Nothing is downloaded, and a merchant never writes code — they
 * pick a template and fill in their own products.
 *
 * Every template must work right-to-left. Arabic is a target market, and
 * retrofitting that later means touching every page.
 *
 * 'always' means every plan includes it, so a shop can never end up with no
 * shop front at all. Everything else is switched on per plan by staff.
 */

return [

    'classic' => [
        'name' => 'Classic',
        'blurb' => 'A plain, quick shop page that suits any kind of product.',
        'best_for' => 'Any shop',
        'accent' => '#5b3df5',
        'always' => true,
        'features' => [
            'Works for any kind of product',
            'Fast on a slow connection',
            'Reads right-to-left for Arabic',
        ],
    ],

    'grocery' => [
        'name' => 'Grocery',
        'blurb' => 'Built for food and daily shopping, the way the delivery apps look. Customers set where they are, and see what can actually reach them.',
        'best_for' => 'Grocery, food and daily needs',
        'accent' => '#16a34a',
        'always' => false,
        'features' => [
            'Customers pick where they are',
            'Shows only what you deliver to them',
            'Categories across the top, prices large',
            'Reads right-to-left for Arabic',
        ],
    ],

];
