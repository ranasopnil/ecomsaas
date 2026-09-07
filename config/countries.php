<?php

/*
 * The markets the platform sells in. Gateways are allowed per country.
 *
 * 'centre' is where a map opens before a shopkeeper has chosen anything, so
 * they start looking at their own country rather than the middle of the ocean.
 */

return [
    'BD' => ['name' => 'Bangladesh', 'currency' => 'BDT', 'centre' => [23.8103, 90.4125], 'zoom' => 11],
    'MY' => ['name' => 'Malaysia', 'currency' => 'MYR', 'centre' => [3.1390, 101.6869], 'zoom' => 11],
    'ID' => ['name' => 'Indonesia', 'currency' => 'IDR', 'centre' => [-6.2088, 106.8456], 'zoom' => 11],
    'AE' => ['name' => 'United Arab Emirates', 'currency' => 'AED', 'centre' => [25.2048, 55.2708], 'zoom' => 11],
    'SA' => ['name' => 'Saudi Arabia', 'currency' => 'SAR', 'centre' => [24.7136, 46.6753], 'zoom' => 11],
];
