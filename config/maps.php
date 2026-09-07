<?php

/*
 * The maps a shopkeeper can pick a delivery area on.
 *
 * Which one a shop gets is the platform's decision, not the shop's: one costs
 * money per map opened and the other does not. Staff choose per shop.
 */

return [

    'default' => env('MAP_DEFAULT_PROVIDER', 'osm'),

    'providers' => [

        'osm' => [
            'name' => 'OpenStreetMap',
            'blurb' => 'Free, with no account and nothing to pay.',
            'needs_key' => false,
        ],

        'google' => [
            'name' => 'Google Maps',
            'blurb' => 'The familiar map, with the best local detail. Google charges the platform for each map opened.',
            'needs_key' => true,
        ],

    ],

];
