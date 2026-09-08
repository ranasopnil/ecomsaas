<?php

/*
 * Couriers a Bangladeshi shop is likely to use, offered as a starting list.
 *
 * Nothing here is forced on a shop: these are only suggestions the shopkeeper
 * can add in one press, and every shop can add, rename or remove its own.
 * {code} in a tracking address is replaced with the consignment number.
 *
 * Add a market's couriers here and they appear as suggestions for shops in
 * that country.
 */

return [

    'BD' => [
        ['name' => 'Pathao Courier', 'tracking_url' => 'https://merchant.pathao.com/tracking?consignment_id={code}'],
        ['name' => 'Steadfast Courier', 'tracking_url' => 'https://steadfast.com.bd/t/{code}'],
        ['name' => 'RedX', 'tracking_url' => 'https://redx.com.bd/track-parcel/?trackingId={code}'],
        ['name' => 'Paperfly', 'tracking_url' => 'https://go.paperfly.com.bd/track/{code}'],
        ['name' => 'eCourier', 'tracking_url' => 'https://ecourier.com.bd/track?id={code}'],
        ['name' => 'Sundarban Courier', 'tracking_url' => null],
        ['name' => 'SA Paribahan', 'tracking_url' => null],
        ['name' => 'Own delivery man', 'tracking_url' => null],
    ],

];
